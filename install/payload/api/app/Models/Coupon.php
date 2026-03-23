<?php

/**
 * Coupon Model
 * Handles coupon validation, usage tracking, and business logic
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Coupon extends Model {
    protected string $table = 'coupons';
    
    public ?int $id = null;
    public ?string $code = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $discount_type = null; // 'percentage' or 'fixed'
    public ?float $discount_value = null;
    public ?float $minimum_order_amount = null;
    public ?float $maximum_discount_amount = null;
    public ?int $usage_limit = null;
    public ?int $usage_limit_per_user = null;
    public ?int $used_count = null;
    public ?string $valid_from = null;
    public ?string $valid_until = null;
    public ?bool $is_active = null;
    public ?string $applicable_to = null; // 'all', 'products', 'combo_packs', 'specific'
    public ?string $applicable_ids = null; // JSON array
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Find coupon by code
     */
    public static function findByCode(string $code): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Validate coupon for a given cart/order
     * @param string $code Coupon code
     * @param array $items Cart items with product_id, combo_pack_id, and total_price
     * @param int|null $userId User ID for per-user usage limit
     * @return array ['valid' => bool, 'coupon' => Coupon|null, 'discount_amount' => float, 'message' => string]
     */
    public static function validate(string $code, array $items = [], ?int $userId = null): array {
        $coupon = self::findByCode($code);
        
        if (!$coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount_amount' => 0,
                'message' => 'Invalid coupon code'
            ];
        }
        
        // Check validity dates
        $now = date('Y-m-d H:i:s');
        if ($coupon->valid_from && $now < $coupon->valid_from) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount_amount' => 0,
                'message' => 'Coupon is not yet valid'
            ];
        }
        
        if ($coupon->valid_until && $now > $coupon->valid_until) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount_amount' => 0,
                'message' => 'Coupon has expired'
            ];
        }
        
        // Calculate subtotal - only filter by restrictions if coupon has them
        $applicableSubtotal = 0.0;
        $hasRestrictions = $coupon->applicable_to !== 'all' && !empty($coupon->applicable_to);
        
        if ($hasRestrictions) {
            // Filter items based on coupon restrictions and calculate applicable subtotal
            $applicableItems = [];
            
            foreach ($items as $item) {
                $itemTotal = (float)($item['total_price'] ?? 0);
                
                // Normalize product_id and combo_pack_id - handle various formats
                $productId = null;
                if (isset($item['product_id'])) {
                    $pid = $item['product_id'];
                    if ($pid !== null && $pid !== '' && $pid !== '0' && (int)$pid > 0) {
                        $productId = (int)$pid;
                    }
                }
                
                $comboPackId = null;
                if (isset($item['combo_pack_id'])) {
                    $cpid = $item['combo_pack_id'];
                    if ($cpid !== null && $cpid !== '' && $cpid !== '0' && (int)$cpid > 0) {
                        $comboPackId = (int)$cpid;
                    }
                }
                
                // Determine if item is applicable based on coupon rules
                $isApplicable = false;
                if ($coupon->applicable_to === 'products') {
                    // Product is applicable if product_id exists and is valid
                    $isApplicable = $productId !== null;
                } elseif ($coupon->applicable_to === 'combo_packs') {
                    // Combo pack is applicable if combo_pack_id exists and is valid
                    $isApplicable = $comboPackId !== null;
                } elseif ($coupon->applicable_to === 'specific') {
                    $applicableIds = $coupon->applicable_ids ? json_decode($coupon->applicable_ids, true) : [];
                    $itemId = $productId ?? $comboPackId ?? null;
                    $isApplicable = $itemId !== null && in_array($itemId, $applicableIds);
                }
                
                if ($isApplicable) {
                    $applicableItems[] = $item;
                    $applicableSubtotal += $itemTotal;
                }
            }
            
            // For 'specific' type, must have applicable items
            if ($coupon->applicable_to === 'specific' && empty($applicableItems)) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0,
                    'message' => 'This coupon is not applicable to items in your cart'
                ];
            }
            
            // For 'products' and 'combo_packs', if no applicable items, check minimum requirement
            if (empty($applicableItems)) {
                // Debug: Count items with product_id vs combo_pack_id
                $productCount = 0;
                $comboPackCount = 0;
                $totalCartValue = 0;
                foreach ($items as $debugItem) {
                    $pid = $debugItem['product_id'] ?? null;
                    $cpid = $debugItem['combo_pack_id'] ?? null;
                    if ($pid !== null && $pid !== '' && $pid !== '0' && (int)$pid > 0) {
                        $productCount++;
                    }
                    if ($cpid !== null && $cpid !== '' && $cpid !== '0' && (int)$cpid > 0) {
                        $comboPackCount++;
                    }
                    $totalCartValue += (float)($debugItem['total_price'] ?? 0);
                }
                
                // If minimum order amount is required but no applicable items exist, show appropriate message
                if ($coupon->minimum_order_amount) {
                    $applicableType = $coupon->applicable_to === 'products' ? 'products' : 'combo packs';
                    $debugInfo = " (Found {$productCount} products, {$comboPackCount} combo packs, Total: ₹" . number_format($totalCartValue, 2) . ")";
                    return [
                        'valid' => false,
                        'coupon' => $coupon,
                        'discount_amount' => 0,
                        'message' => "This coupon requires minimum ₹{$coupon->minimum_order_amount} in {$applicableType}. Add {$applicableType} to your cart to use this coupon." . $debugInfo
                    ];
                }
                // No minimum requirement, just return discount 0
                return [
                    'valid' => true,
                    'coupon' => $coupon,
                    'discount_amount' => 0,
                    'message' => $coupon->applicable_to === 'products' 
                        ? 'This coupon applies to products only. Add products to your cart to get the discount.'
                        : 'This coupon applies to combo packs only. Add combo packs to your cart to get the discount.'
                ];
            }
            
            // Check minimum order amount based on applicable items subtotal only (when restricted)
            if ($coupon->minimum_order_amount && $applicableSubtotal < $coupon->minimum_order_amount) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0,
                    'message' => "Minimum order amount of ₹{$coupon->minimum_order_amount} required for applicable items. Current applicable amount: ₹" . number_format($applicableSubtotal, 2)
                ];
            }
        } else {
            // No restrictions - calculate subtotal from all items
            foreach ($items as $item) {
                $applicableSubtotal += (float)($item['total_price'] ?? 0);
            }
            
            // Check minimum order amount based on full cart subtotal (when no restrictions)
            if ($coupon->minimum_order_amount && $applicableSubtotal < $coupon->minimum_order_amount) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0,
                    'message' => "Minimum order amount of ₹{$coupon->minimum_order_amount} required. Current cart total: ₹" . number_format($applicableSubtotal, 2)
                ];
            }
        }
        
        // Check total usage limit
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount_amount' => 0,
                'message' => 'Coupon usage limit exceeded'
            ];
        }
        
        // Check per-user usage limit
        if ($userId && $coupon->usage_limit_per_user) {
            $userUsageCount = self::getUserUsageCount($coupon->id, $userId);
            if ($userUsageCount >= $coupon->usage_limit_per_user) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0,
                    'message' => 'You have already used this coupon'
                ];
            }
        }
        
        // Calculate discount amount based on applicable items subtotal only
        $discountAmount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discountAmount = ($applicableSubtotal * $coupon->discount_value) / 100;
            if ($coupon->maximum_discount_amount) {
                $discountAmount = min($discountAmount, $coupon->maximum_discount_amount);
            }
        } else {
            $discountAmount = $coupon->discount_value;
        }
        
        // Ensure discount doesn't exceed applicable subtotal
        $discountAmount = min($discountAmount, $applicableSubtotal);
        
        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount_amount' => round($discountAmount, 2),
            'message' => 'Coupon applied successfully'
        ];
    }
    
    /**
     * Get usage count for a specific user
     */
    public static function getUserUsageCount(int $couponId, int $userId): int {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM coupon_usages WHERE coupon_id = ? AND user_id = ?");
        $stmt->execute([$couponId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * Record coupon usage
     */
    public static function recordUsage(int $couponId, ?int $userId, ?int $orderId, float $discountAmount): void {
        $conn = Database::getConnection();
        
        // Insert usage record
        $stmt = $conn->prepare("
            INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$couponId, $userId, $orderId, $discountAmount]);
        
        // Update coupon used_count
        $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
        $stmt->execute([$couponId]);
    }
    
    /**
     * Get all active coupons
     */
    public static function getActiveCoupons(): array {
        $conn = Database::getConnection();
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("
            SELECT * FROM coupons 
            WHERE is_active = 1 
            AND (valid_from IS NULL OR valid_from <= ?)
            AND (valid_until IS NULL OR valid_until >= ?)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$now, $now]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return new self($row);
        }, $results);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        
        // Convert applicable_ids JSON to array
        if (!empty($array['applicable_ids'])) {
            $array['applicable_ids'] = json_decode($array['applicable_ids'], true);
        }
        
        return $array;
    }
}

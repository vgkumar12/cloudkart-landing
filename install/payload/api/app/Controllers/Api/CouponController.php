<?php

/**
 * Coupon API Controller
 * Handles coupon validation for frontend
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Helpers\SessionHelper;
use App\Models\Coupon;
use App\Models\Cart;

class CouponController extends Controller {
    
    /**
     * Validate coupon code
     * POST /api/coupons/validate
     */
    public function validate(): void {
        // Ensure correct session is started (should be frontend for coupons)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        try {
            $data = $this->request->all();
            $code = $data['code'] ?? '';
            $sessionId = $data['session_id'] ?? session_id();
            
            // Get user_id from session (same as CartController does)
            $userId = $_SESSION['user_id'] ?? ($this->request->user['id'] ?? null);
            
            if (empty($code)) {
                $this->validationError(['code' => ['Coupon code is required']], 'Validation failed');
                return;
            }
            
            // Get cart items using same logic as CartController
            // Cart::getItems expects userId directly (the user_id from cart table, not customer_id)
            // It will fetch by user_id if provided, otherwise by session_id
            $cartItems = Cart::getItems($sessionId, $userId);
            
            // Prepare items array with full details for coupon validation
            $items = [];
            foreach ($cartItems as $item) {
                // Extract and normalize product_id and combo_pack_id
                // Handle various formats: null, 0, '0', empty string, or actual ID
                $productId = null;
                if (isset($item['product_id'])) {
                    $pid = $item['product_id'];
                    // Check if it's a valid positive integer ID
                    if ($pid !== null && $pid !== '' && $pid !== '0' && $pid !== 0) {
                        $pidInt = (int)$pid;
                        if ($pidInt > 0) {
                            $productId = $pidInt;
                        }
                    }
                }
                
                $comboPackId = null;
                if (isset($item['combo_pack_id'])) {
                    $cpid = $item['combo_pack_id'];
                    // Check if it's a valid positive integer ID
                    if ($cpid !== null && $cpid !== '' && $cpid !== '0' && $cpid !== 0) {
                        $cpidInt = (int)$cpid;
                        if ($cpidInt > 0) {
                            $comboPackId = $cpidInt;
                        }
                    }
                }
                
                // Calculate total_price if not present or use existing
                $totalPrice = (float)($item['total_price'] ?? 0);
                // Fallback: calculate from price * quantity if total_price is missing
                if ($totalPrice == 0 && isset($item['price']) && isset($item['quantity'])) {
                    $totalPrice = (float)$item['price'] * (int)$item['quantity'];
                }
                
                $items[] = [
                    'product_id' => $productId,
                    'combo_pack_id' => $comboPackId,
                    'total_price' => $totalPrice
                ];
            }
            
            // Validate coupon (it will calculate applicable subtotal internally)
            $result = Coupon::validate($code, $items, $userId);
            
            if ($result['valid']) {
                $this->success([
                    'coupon' => $result['coupon']->toArray(),
                    'discount_amount' => $result['discount_amount'],
                    'message' => $result['message']
                ], 'Coupon validated successfully');
            } else {
                $this->error($result['message'], 400);
            }
        } catch (\Exception $e) {
            $this->error('Failed to validate coupon: ' . $e->getMessage(), 500);
        }
    }
}

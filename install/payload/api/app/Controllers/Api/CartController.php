<?php

/**
 * Cart Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Helpers\SessionHelper;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ComboPack;

class CartController extends Controller {
    
    /**
     * Get cart items
     * GET /api/cart
     */
    public function index(): void {
        // Ensure correct session is started (should be frontend for cart)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        // Prioritize session_id from request (frontend generated UUID)
        $sessionId = $this->request->get('session_id') ?? $this->request->input('session_id');
        /*
         * Fallback to PHP session_id() ONLY if frontend didn't send one.
         * This handles legacy/direct API calls, but frontend should always send one now.
         */
        if (empty($sessionId)) {
            $sessionId = session_id();
        }
        
        $userId = $_SESSION['user_id'] ?? ($this->request->get('user_id') ? (int)$this->request->get('user_id') : null);
        
        error_log("CartController::index - Debug: PHP_SessionID=" . session_id() . " | Req_SessionID=" . $sessionId . " | UserID=" . ($userId ?? 'NULL') . " | Session_Dump=" . json_encode($_SESSION));

        try {
            $summary = Cart::getSummary($sessionId, $userId);
            // Include session_id in response data so frontend can use it
            $responseData = $summary;
            $responseData['session_id'] = $sessionId;
            $this->success($responseData, 'Cart retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve cart: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Add item to cart
     * POST /api/cart
     */
    public function store(): void {
        // Ensure correct session is started (should be frontend for cart)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        try {
            // Validate that either product_id or combo_pack_id is provided
            $productId = $this->request->input('product_id');
            $comboPackId = $this->request->input('combo_pack_id');
            
            if (!$productId && !$comboPackId) {
                $this->validationError(['product_id' => 'Either product_id or combo_pack_id is required'], 'Either product_id or combo_pack_id is required');
                return;
            }
            
            $data = $this->request->validate([
                'product_id' => 'integer',
                'variant_id' => 'integer',
                'combo_pack_id' => 'integer',
                'quantity' => 'required|integer|min:1',
                'session_id' => 'string',
                'user_id' => 'integer'
            ]);
            
            // Prioritize session_id from request input
            $sessionId = $data['session_id'] ?? null;
            if (empty($sessionId)) {
                 $sessionId = session_id();
            }
            
            $userId = $_SESSION['user_id'] ?? ($data['user_id'] ?? null);
            
            $cartData = [];
            $itemType = 'product';
            
            if ($productId) {
                // Handle product
                $product = Product::findById($productId);
                if (!$product) {
                    $this->notFound('Product not found');
                    return;
                }
                
                if (!$product->isInStock()) {
                    $this->error('Product is out of stock', 400);
                    return;
                }
                
                $price = $product->getDiscountedPrice();
                
                // If variant_id is provided, fetch variant price
                if (!empty($data['variant_id'])) {
                    $conn = \App\Core\Database::getConnection();
                    $stmt = $conn->prepare("SELECT price, sale_price FROM product_variants WHERE id = ? AND is_active = 1");
                    $stmt->execute([$data['variant_id']]);
                    $variant = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($variant) {
                        // Use variant sale_price if available, otherwise variant price
                        if (isset($variant['sale_price']) && $variant['sale_price'] > 0) {
                            $price = (float)$variant['sale_price'];
                        } elseif (isset($variant['price']) && $variant['price'] > 0) {
                            $price = (float)$variant['price'];
                        }
                    }
                }

                $cartData = [
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'variant_id' => $data['variant_id'] ?? null,
                    'combo_pack_id' => null,
                    'quantity' => $data['quantity'],
                    'price' => $price,
                    'total_price' => $price * $data['quantity'],
                    'item_type' => 'product'
                ];
            } elseif ($comboPackId) {
                // Handle combo pack
                $comboPack = ComboPack::findById($comboPackId);
                if (!$comboPack) {
                    $this->notFound('Combo pack not found');
                    return;
                }
                
                if (!$comboPack->is_active) {
                    $this->error('Combo pack is not available', 400);
                    return;
                }
                
                $cartData = [
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'product_id' => null,
                    'combo_pack_id' => $comboPackId,
                    'quantity' => $data['quantity'],
                    'price' => $comboPack->price ?? 0,
                    'total_price' => ($comboPack->price ?? 0) * $data['quantity'],
                    'item_type' => 'combo_pack'
                ];
            }
            
            $cartItem = Cart::addItem($cartData);
            $result = $cartItem->toArray();
            // Include session_id in the response data
            $responseData = [
                'item' => $result,
                'session_id' => $sessionId
            ];
            $this->success($responseData, 'Item added to cart', 201);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $this->validationError($errors ?? [], $e->getMessage());
        } catch (\Exception $e) {
            $this->error('Failed to add item to cart: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update cart item
     * PUT /api/cart/{id}
     */
    public function update(int $id): void {
        // Ensure correct session is started (should be frontend for cart)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        try {
            $cartItem = Cart::findById($id);
            
            if (!$cartItem) {
                $this->notFound('Cart item not found');
                return;
            }
            
            // Validate ownership - check session_id or user_id
            $sessionId = $this->request->get('session_id') ?? $this->request->input('session_id');
            if (empty($sessionId)) {
                $sessionId = session_id();
            }
            
            $userId = $_SESSION['user_id'] ?? ($this->request->get('user_id') ? (int)$this->request->get('user_id') : ($this->request->input('user_id') ? (int)$this->request->input('user_id') : null));
            
            // Verify cart item belongs to current session or user
            $isOwner = false;
            if ($userId && $cartItem->user_id && (int)$cartItem->user_id === (int)$userId) {
                $isOwner = true;
            } elseif (!$userId && $cartItem->session_id && $cartItem->session_id === $sessionId) {
                $isOwner = true;
            }
            
            if (!$isOwner) {
                $this->error('Unauthorized: This cart item does not belong to your session', 403);
                return;
            }
            
            $quantity = (int)($this->request->input('quantity', 1));
            
            if ($quantity <= 0) {
                // Remove item
                $cartItem->delete();
                $this->success(null, 'Item removed from cart');
                return;
            }

            // CONSOLIDATION LOGIC: Check if there are OTHER rows for this same item
            // This fixes jumping quantities if duplicates already exist in the database
            $conn = \App\Core\Database::getConnection();
            $sql = "SELECT id, quantity FROM cart 
                    WHERE id != ? 
                    AND session_id = ? 
                    AND product_id <=> ? 
                    AND variant_id <=> ? 
                    AND combo_pack_id <=> ? 
                    AND item_type = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $cartItem->id,
                $cartItem->session_id,
                $cartItem->product_id,
                $cartItem->variant_id,
                $cartItem->combo_pack_id,
                $cartItem->item_type
            ]);
            
            $duplicates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($duplicates)) {
                // If we found duplicates, we don't necessarily want to SUM them here
                // because the user is SETTING the quantity to a specific value from the UI.
                // However, the UI's 'newQuantity' was calculated based on the SUM of all duplicates.
                // So updating this one row to the 'newQuantity' is correct, 
                // but we MUST delete the others so they don't get added back in the next UI grouping.
                foreach ($duplicates as $dup) {
                    $delStmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
                    $delStmt->execute([$dup['id']]);
                }
            }
            
            $cartItem->update([
                'quantity' => $quantity,
                'total_price' => (float)$cartItem->price * (int)$quantity
            ]);
            
            $this->success($cartItem->toArray(), 'Cart item updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update cart item: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Remove item from cart
     * DELETE /api/cart/{id}
     */
    public function destroy(int $id): void {
        // Ensure correct session is started (should be frontend for cart)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        try {
            $cartItem = Cart::findById($id);
            
            if (!$cartItem) {
                $this->notFound('Cart item not found');
                return;
            }
            
            // Validate ownership - check session_id or user_id
            $sessionId = $this->request->get('session_id') ?? $this->request->input('session_id');
            if (empty($sessionId)) {
                $sessionId = session_id();
            }

            $userId = $_SESSION['user_id'] ?? ($this->request->get('user_id') ? (int)$this->request->get('user_id') : ($this->request->input('user_id') ? (int)$this->request->input('user_id') : null));
            
            // Verify cart item belongs to current session or user
            $isOwner = false;
            if ($userId && $cartItem->user_id && (int)$cartItem->user_id === (int)$userId) {
                $isOwner = true;
            } elseif (!$userId && $cartItem->session_id && $cartItem->session_id === $sessionId) {
                $isOwner = true;
            }
            
            if (!$isOwner) {
                $this->error('Unauthorized: This cart item does not belong to your session', 403);
                return;
            }
            
            $cartItem->delete();
            $this->success(null, 'Item removed from cart');
        } catch (\Exception $e) {
            $this->error('Failed to remove cart item: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Clear cart
     * DELETE /api/cart
     */
    public function clear(): void {
        // Ensure correct session is started (should be frontend for cart)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        $sessionId = $this->request->get('session_id') ?? $this->request->input('session_id');
        if (empty($sessionId)) {
            $sessionId = session_id();
        }

        $userId = $_SESSION['user_id'] ?? ($this->request->get('user_id') ? (int)$this->request->get('user_id') : null);
        
        try {
            Cart::clear($sessionId, $userId);
            $this->success(null, 'Cart cleared successfully');
        } catch (\Exception $e) {
            $this->error('Failed to clear cart: ' . $e->getMessage(), 500);
        }
    }
}




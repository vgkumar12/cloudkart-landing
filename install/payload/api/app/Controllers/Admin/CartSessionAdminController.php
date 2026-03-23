<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\CartSession;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ComboPack;
use App\Core\Database;

class CartSessionAdminController extends Controller {
    
    /**
     * List all cart sessions
     */
    public function index(): void {
        try {
            // Use Model method
            $sessions = CartSession::getAllWithStats(100);
            
            $this->success($sessions, 'Cart sessions retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve cart sessions: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get cart session details with items
     */
    public function show(string $sessionId): void {
        try {
            if (empty($sessionId)) {
                $this->validationError(['session_id' => ['Session ID required']], 'Validation failed');
                return;
            }
            
            // Use Model method
            $details = CartSession::getWithDetails($sessionId);
            
            if (!$details) {
                $this->notFound('Session not found');
                return;
            }
            
            $this->success($details, 'Cart session retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve cart session: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Clear cart session
     */
    public function clear(string $sessionId): void {
        try {
            if (empty($sessionId)) {
                $this->validationError(['session_id' => ['Session ID required']], 'Validation failed');
                return;
            }
            
            // Clear cart using Model methods
            Cart::clear($sessionId);
            
            // Delete cart session using Model
            $cartSession = CartSession::findBySessionId($sessionId);
            if ($cartSession) {
                $cartSession->delete();
            }
            
            $this->success(null, 'Cart session cleared successfully');
        } catch (\Exception $e) {
            $this->error('Failed to clear cart session: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Convert cart session to order
     */
    public function convertToOrder(): void {
        try {
            $data = $this->request->getBody();
            $sessionId = $data['session_id'] ?? '';
            
            if (empty($sessionId)) {
                $this->validationError(['session_id' => ['Session ID required']], 'Validation failed');
                return;
            }
            
            // Get cart session using Model
            $cartSession = CartSession::findBySessionId($sessionId);
            if (!$cartSession) {
                $this->notFound('Cart session not found');
                return;
            }
            $session = $cartSession->toArray();
            
            // Get cart items using Model
            $cartItems = Cart::getItems($sessionId);
            
            if (empty($cartItems)) {
                $this->validationError(['cart' => ['Cart is empty']], 'Validation failed');
                return;
            }
            
            // Find or create customer using Model
            $customerId = $session['user_id'];
            if (!$customerId) {
                $customerData = [
                    'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                    'email' => $data['email'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'address' => $data['address'] ?? '',
                    'city' => $data['city'] ?? '',
                    'state' => $data['state'] ?? '',
                    'pincode' => $data['pincode'] ?? '',
                ];
                
                $customer = Customer::findOrCreate($customerData);
                $customerId = $customer->id;
            }
            
            // Prepare order items
            $orderItems = [];
            foreach ($cartItems as $item) {
                $itemName = '';
                if ($item['product_id']) {
                    $product = Product::findById($item['product_id']);
                    $itemName = $product ? $product->name : 'Product';
                } elseif ($item['combo_pack_id']) {
                    $combo = ComboPack::findById($item['combo_pack_id']);
                    $itemName = $combo ? $combo->name : 'Combo Pack';
                }
                
                $orderItems[] = [
                    'product_id' => $item['product_id'] ?: null,
                    'combo_pack_id' => $item['combo_pack_id'] ?: null,
                    'item_name' => $itemName,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['total_price']
                ];
            }
            
            $shippingAddress = json_encode([
                'name' => $data['first_name'] . ' ' . $data['last_name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'pincode' => $data['pincode']
            ]);
            
            // Create order with items using Model
            $orderData = [
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customerId,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'order_source' => 'online',
                'subtotal' => $session['total_amount'],
                'discount_amount' => $session['discount_amount'] ?? 0,
                'total_amount' => $session['final_amount'],
                'shipping_address' => $shippingAddress,
                'billing_address' => $shippingAddress,
                'payment_method' => $data['payment_method'] ?? 'cod',
                'notes' => $session['notes'] ?? '',
                'order_date' => date('Y-m-d H:i:s')
            ];
            
            $order = Order::createWithItems($orderData, $orderItems);
            
            // Clear cart using Model methods
            Cart::clear($sessionId);
            $cartSession = CartSession::findBySessionId($sessionId);
            if ($cartSession) {
                $cartSession->delete();
            }
            
            $this->success([
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ], 'Order created successfully');
        } catch (\Exception $e) {
            $this->error('Failed to convert cart to order: ' . $e->getMessage(), 500);
        }
    }
}


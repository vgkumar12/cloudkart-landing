<?php

/**
 * Order Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Helpers\SessionHelper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Cart;

class OrderController extends Controller {
    
    /**
     * Get all orders
     * GET /api/orders
     */
    public function index(): void {
        // Ensure correct session is started (should be frontend for customer orders)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        // If user is authenticated, filter by their customer_id
        $customerId = null;
        if (isset($this->request->user['id'])) {
            // User is authenticated - get their customer_id
            $userId = $this->request->user['id'];
            $customer = Customer::findByUserId($userId);
            if ($customer) {
                $customerId = $customer->id;
            }
        } else {
            // Allow explicit customer_id in query for admin/other purposes
            $customerId = $this->request->get('customer_id') ? (int)$this->request->get('customer_id') : null;
        }
        
        $status = $this->request->get('status');
        $limit = (int)($this->request->get('limit') ?? 50);
        
        try {
            $orders = Order::getAll($customerId, $status, $limit);
            $this->success($orders, 'Orders retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve orders: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single order
     * GET /api/orders/{id}
     */
    public function show(int $id): void {
        // Ensure correct session is started (should be frontend for customer orders)
        $context = SessionHelper::getContext($this->request->path());
        SessionHelper::startSession($context);
        
        try {
            $order = Order::findById($id);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            // If user is authenticated, ensure they can only view their own orders
            if (isset($this->request->user['id'])) {
                $userId = $this->request->user['id'];
                $userRole = $this->request->user['role'] ?? 'customer';
                
                // Admins can view any order
                if ($userRole !== 'admin') {
                    $customer = Customer::findByUserId($userId);
                    if (!$customer || (int)$order->customer_id !== (int)$customer->id) {
                        $this->forbidden('You can only view your own orders');
                        return;
                    }
                }
            }
            
            // Fetch items
            $order->items = OrderItem::getByOrderId($id);
            
            $this->success($order->toArray(), 'Order retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create order
     * POST /api/orders
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['customer_id']) && empty($data['customer'])) {
                // If customer details are provided, validate them
                if (empty($data['first_name']) || empty($data['phone']) || empty($data['address'])) {
                     $this->validationError([
                        'first_name' => empty($data['first_name']) ? ['First Name is required'] : [],
                        'phone' => empty($data['phone']) ? ['Phone is required'] : [],
                        'address' => empty($data['address']) ? ['Address is required'] : []
                    ], 'Please fill in all required fields (Name, Phone, Address)');
                    return;
                }
            } else if (empty($data['customer_id']) && empty($data['customer'])) {
                // Keep original check for robustness
                $this->validationError(['customer_id' => ['Customer is required']], 'Validation failed');
                return;
            }
            
            // Handle customer creation/update
            $customerId = $data['customer_id'] ?? null;
            if (!$customerId && !empty($data['customer'])) {
                $customer = Customer::findOrCreate($data['customer']);
                $customerId = $customer->id;
            }
            
            // Get cart items if session_id provided
            $items = $data['items'] ?? [];
            if (empty($items) && !empty($data['session_id'])) {
                // Determine userId for cart lookup
                // If the user making the request is logged in, use their ID
                $cartUserId = $this->request->user['id'] ?? null;
                
                // If it's an admin request creating an order for a customer, 
                // we might need to handle that differently, but for now let's prioritize
                // the session_id lookup by passing null if not the user's own cart
                if (!$cartUserId && empty($data['user_id'])) {
                     $cartUserId = null;
                }
                
                $cartItems = Cart::getItems($data['session_id'], $cartUserId);
                foreach ($cartItems as $cartItem) {
                    $items[] = [
                        'product_id' => $cartItem['product_id'],
                        'combo_pack_id' => $cartItem['combo_pack_id'],
                        'item_name' => $cartItem['item_name'] ?? 'Product',
                        'quantity' => $cartItem['quantity'],
                        'unit_price' => $cartItem['price'],
                        'total_price' => $cartItem['total_price']
                    ];
                }
            }
            
            if (empty($items)) {
                $this->validationError(['items' => ['Order items are required']], 'Validation failed');
                return;
            }
            
            // Calculate totals
            $subtotal = array_sum(array_column($items, 'total_price'));
            $discountAmount = (float)($data['discount_amount'] ?? 0);
            $deliveryCharge = (float)($data['delivery_charge'] ?? 0);
            $totalAmount = $subtotal - $discountAmount + $deliveryCharge;
            
            // Prepare order data
            $orderData = [
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customerId,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'delivery_type' => $data['delivery_type'] ?? 'free',
                'delivery_charge' => $deliveryCharge,
                'payment_method' => $data['payment_method'] ?? 'cod',
                'payment_status' => $data['payment_status'] ?? 'pending',
                'order_status' => $data['order_status'] ?? 'pending',
                'order_source' => $data['order_source'] ?? 'online',
                'preferred_delivery_date' => $data['preferred_delivery_date'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null
            ];
            
            // Create order with items
            $order = Order::createWithItems($orderData, $items);
            
            // Send WhatsApp Notification
            try {
                 if (\App\Models\Setting::get('whatsapp_notify_order_confirm', '0') === '1') {
                    $waService = new \App\Services\WhatsAppService();
                    $orderInfo = $order->toArray();
                    
                    // Attach customer details
                    if (isset($customer)) {
                        $orderInfo['customer_name'] = $customer->name;
                        $orderInfo['customer_phone'] = $customer->phone;
                    } elseif (!empty($data['customer'])) {
                         $orderInfo['customer_name'] = $data['customer']['name'] ?? '';
                         $orderInfo['customer_phone'] = $data['customer']['phone'] ?? '';
                    } elseif ($customerId) {
                         $c = Customer::findById($customerId);
                         if ($c) {
                             $orderInfo['customer_name'] = $c->name;
                             $orderInfo['customer_phone'] = $c->phone;
                         }
                    }

                    $orderInfo['quantity'] = count($items);
                    $waService->sendOrderConfirmation($orderInfo);
                 }
            } catch (\Exception $e) {
                error_log("WhatsApp Error: " . $e->getMessage());
            }
            
            // Clear cart if session_id provided
            if (!empty($data['session_id'])) {
                $cartUserId = $this->request->user['id'] ?? null;
                Cart::clear($data['session_id'], $cartUserId);
            }
            
            $this->success($order->toArray(), 'Order created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update order status
     * PUT /api/orders/{id}/status
     */
    public function updateStatus(int $id): void {
        try {
            $order = Order::findById($id);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            $status = $this->request->input('status');
            $paymentStatus = $this->request->input('payment_status');
            
            $updateData = [];
            if ($status) {
                $updateData['order_status'] = $status;
            }
            if ($paymentStatus) {
                $updateData['payment_status'] = $paymentStatus;
            }
            
            if (!empty($updateData)) {
                $order->update($updateData);
            }
            
            $this->success($order->toArray(), 'Order status updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update order status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Checkout (create order from cart)
     * POST /api/orders/checkout
     */
    public function checkout(): void {
        try {
            // Ensure correct session is started (should be frontend for checkout)
            $context = SessionHelper::getContext($this->request->path());
            SessionHelper::startSession($context);
            
            $userId = $this->request->user['id'] ?? null;
            
            if (!$userId) {
                $this->unauthorized('Authentication required');
                return;
            }
            
            $data = $this->request->all();
            
            // Get customer using Model
            $customer = \App\Models\Customer::findByUserId($userId);
            
            if (!$customer) {
                $this->notFound('Customer profile not found');
                return;
            }
            
            $customerId = $customer->id;
            
            // Validate required fields (Name, Phone, Address)
            // They must be present in request OR already in customer profile
            $phone = !empty($data['phone']) ? $data['phone'] : $customer->phone;
            $address = !empty($data['address']) ? $data['address'] : $customer->address;
            $nameToCheck = !empty($data['first_name']) ? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) : $customer->name;
            
            if (empty($phone) || empty($address) || empty($nameToCheck)) {
                $this->validationError([
                    'phone' => empty($phone) ? ['Phone is required'] : [],
                    'address' => empty($address) ? ['Address is required'] : [],
                    'first_name' => empty($nameToCheck) ? ['Name is required'] : []
                ], 'Please provide Name, Phone and Address to continue.');
                return;
            }
            
            // Update customer details if provided
            $customerUpdateData = [];
            
            // Allow updating Name and Phone if they differ
            if (!empty($nameToCheck) && $customer->name !== $nameToCheck) {
                // Determine if name is stored as single string or split
                // Assuming 'name' column based on model usage
                $customerUpdateData['name'] = $nameToCheck;
            }
            if (!empty($phone) && $customer->phone !== $phone) {
                $customerUpdateData['phone'] = $phone;
            }
            
            if (!empty($data['address']) && $customer->address !== $data['address']) {
                $customerUpdateData['address'] = $data['address'];
            }
            if (!empty($data['city']) && $customer->city !== $data['city']) {
                $customerUpdateData['city'] = $data['city'];
            }
            if (!empty($data['state']) && $customer->state !== $data['state']) {
                $customerUpdateData['state'] = $data['state'];
            }
            if (!empty($data['pincode']) && $customer->pincode !== $data['pincode']) {
                $customerUpdateData['pincode'] = $data['pincode'];
            }
            
            if (!empty($customerUpdateData)) {
                // Use Model's instance update method
                $customer->update($customerUpdateData);
            }
            if (!empty($data['pincode']) && $customer->pincode !== $data['pincode']) {
                $customerUpdateData['pincode'] = $data['pincode']; // NOTE: schema might not have state/zip if not seen in Model props, check Model props
            }
            if (!empty($data['phone']) && $customer->phone !== $data['phone']) {
                $customerUpdateData['phone'] = $data['phone'];
            }
             // Handle name if split in form (first, last) vs single in DB
            $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            if (!empty($name) && $customer->name !== $name) {
                $customerUpdateData['name'] = $name;
            }
            
            if (!empty($customerUpdateData)) {
                $customer->update($customerUpdateData);
            }
            
            // Get cart items
            // Prioritize session_id from request (frontend unique token)
            $sessionId = $data['session_id'] ?? $this->request->get('session_id'); 
            
            // Fallback only if absolutely necessary (though frontend should always send it)
            if (empty($sessionId)) {
                 $sessionId = session_id(); 
            }

            // FIX: Pass userId instead of customerId. Cart model expects userId for logged-in users.
            error_log("OrderController::checkout - Fetching cart items. SessionID: " . ($sessionId ?? 'null') . " | UserID: " . ($userId ?? 'null'));
            
            $cartItems = Cart::getItems($sessionId, $userId);
            
            // Log count found
            error_log("OrderController::checkout - Found " . count($cartItems) . " items.");

            if (empty($cartItems)) {
                $this->validationError(['cart' => ['Cart is empty']], 'Validation failed');
                return;
            }
            
            // Prepare order items
            $items = [];
            // Aggregate and prepare order items
            $groupedItems = [];
            foreach ($cartItems as $cartItem) {
                // Create a unique key for grouping (Product vs Combo)
                $key = $cartItem['product_id'] ? 'p_' . $cartItem['product_id'] : 'c_' . $cartItem['combo_pack_id'];
                
                if (!isset($groupedItems[$key])) {
                    $groupedItems[$key] = [
                        'product_id' => $cartItem['product_id'],
                        'combo_pack_id' => $cartItem['combo_pack_id'],
                        // Fix: use 'name' from Cart model, fallback to 'item_name' or 'Product'
                        'item_name' => $cartItem['name'] ?? $cartItem['item_name'] ?? 'Product', 
                        'quantity' => 0,
                        'unit_price' => $cartItem['price'],
                        'total_price' => 0
                    ];
                }
                
                // Accumulate quantity and price
                $groupedItems[$key]['quantity'] += $cartItem['quantity'];
                $groupedItems[$key]['total_price'] += $cartItem['total_price'];
            }
            
            $items = array_values($groupedItems);
            
            // Calculate totals
            $subtotal = array_sum(array_column($items, 'total_price'));
            $discountAmount = (float)($data['discount_amount'] ?? 0);
            $couponCode = $data['coupon_code'] ?? null;
            $couponId = null;
            
            // Validate and apply coupon if provided
            if ($couponCode) {
                // Prepare items array with total_price for coupon validation
                $couponItems = [];
                foreach ($items as $item) {
                    $couponItems[] = [
                        'product_id' => $item['product_id'] ?? null,
                        'combo_pack_id' => $item['combo_pack_id'] ?? null,
                        'total_price' => (float)($item['total_price'] ?? 0)
                    ];
                }
                $couponValidation = \App\Models\Coupon::validate(
                    $couponCode,
                    $couponItems,
                    $userId
                );
                
                if ($couponValidation['valid']) {
                    $discountAmount = $couponValidation['discount_amount'];
                    $couponId = $couponValidation['coupon']->id;
                } else {
                    $this->validationError(['coupon_code' => [$couponValidation['message']]], 'Invalid coupon code');
                    return;
                }
            }
            
            $deliveryCharge = (float)($data['delivery_charge'] ?? 0);
            $totalAmount = $subtotal - $discountAmount + $deliveryCharge;
            
            // Create order
            $orderData = [
                'customer_id' => $customerId,
                'order_number' => Order::generateOrderNumber(),
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'delivery_type' => $data['delivery_type'] ?? 'standard',
                'delivery_charge' => $deliveryCharge,
                'payment_method' => $data['payment_method'] ?? 'cod',
                'payment_status' => 'pending',
                'order_status' => 'pending',
                'order_source' => 'online',
                'preferred_delivery_date' => $data['preferred_delivery_date'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null
            ];
            
            $order = Order::createWithItems($orderData, $items);
            
            // Record coupon usage if coupon was applied
            if ($couponId && $discountAmount > 0) {
                \App\Models\Coupon::recordUsage($couponId, $userId, $order->id, $discountAmount);
            }

            // Send WhatsApp Notification
            try {
                 if (\App\Models\Setting::get('whatsapp_notify_order_confirm', '0') === '1') {
                    $waService = new \App\Services\WhatsAppService();
                    $orderInfo = $order->toArray();
                    $orderInfo['customer_name'] = $customer->name;
                    $orderInfo['customer_phone'] = $customer->phone;
                    $orderInfo['quantity'] = array_sum(array_column($items, 'quantity'));
                    
                    $waService->sendOrderConfirmation($orderInfo);
                 }
            } catch (\Exception $e) {
                error_log("WhatsApp Error: " . $e->getMessage());
            }
            
            // Clear cart
            Cart::clear($sessionId, $userId);
            
            $this->success($order->toArray(), 'Order created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to checkout: ' . $e->getMessage(), 500);
        }
    }
}



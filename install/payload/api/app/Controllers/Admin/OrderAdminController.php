<?php

/**
 * Order Admin Controller
 * Admin operations for orders
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use App\Models\Product;
use App\Models\ComboPack;
use App\Core\Database;

class OrderAdminController extends Controller {
    
    /**
     * Get order by ID
     * GET /api/admin/orders/{id}
     */
    public function show(int $id): void {
        try {
            $order = Order::findById($id);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            $this->success($order->toArray(), 'Order retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get all orders with filters
     * GET /api/admin/orders
     */
    public function index(): void {
        $search = $this->request->get('q', '') ?: $this->request->get('search', '');
        $status = $this->request->get('status');
        $paymentStatus = $this->request->get('payment_status');
        $orderSource = $this->request->get('order_source');
        $page = (int)($this->request->get('page') ?? 1);
        $limit = (int)($this->request->get('limit') ?? 20);
        $sortBy = $this->request->get('sort_by') ?: 'id';
        $sortOrder = $this->request->get('sort_order') ?: 'DESC';
        
        // Validate pagination parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100;
        
        try {
            // Use Model method with pagination, search, and sorting
            $result = Order::searchWithPagination($search, $status, $paymentStatus, $orderSource, $page, $limit, $sortBy, $sortOrder);
            
            $this->success($result, 'Orders retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve orders: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update order (comprehensive update with change tracking and email notification)
     * PUT /api/admin/orders/{id}
     */
    public function update(int $id): void {
        try {
            $order = Order::findById($id);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            $data = $this->request->getBody();
            $oldOrderData = $order->toArray();
            $changes = [];
            $sendEmail = (bool)($data['send_email_notification'] ?? true);
            
            $conn = Database::getConnection();
            $conn->beginTransaction();
            
            // Track changes for email notification
            $updateData = [];
            
            // Update order status if provided
            if (isset($data['order_status']) && $data['order_status'] !== $order->order_status) {
                $updateData['order_status'] = $data['order_status'];
                $changes['order_status'] = [
                    'old' => $order->order_status,
                    'new' => $data['order_status']
                ];
            }
            
            // Update payment status if provided
            if (isset($data['payment_status']) && $data['payment_status'] !== $order->payment_status) {
                $updateData['payment_status'] = $data['payment_status'];
                $changes['payment_status'] = [
                    'old' => $order->payment_status,
                    'new' => $data['payment_status']
                ];
            }
            
            // Update payment method if provided
            if (isset($data['payment_method']) && $data['payment_method'] !== $order->payment_method) {
                $updateData['payment_method'] = $data['payment_method'];
                $changes['payment_method'] = [
                    'old' => $order->payment_method,
                    'new' => $data['payment_method']
                ];
            }
            
            // Update discount amount if provided
            if (isset($data['discount_amount'])) {
                $newDiscount = (float)$data['discount_amount'];
                if ($newDiscount != ($order->discount_amount ?? 0)) {
                    $changes['discount_amount'] = [
                        'old' => $order->discount_amount ?? 0,
                        'new' => $newDiscount
                    ];
                }
            }
            
            // Update order items if provided
            $itemsChanged = false;
            if (isset($data['items']) || isset($data['new_items'])) {
                $items = $data['items'] ?? [];
                $newItems = $data['new_items'] ?? [];
                
                // Get old items for comparison
                $oldItems = OrderItem::getByOrderId($id);
                
                // Update existing items
                foreach ($items as $row) {
                    $itemId = (int)($row['id'] ?? 0);
                    $quantity = max(0, (int)($row['quantity'] ?? 0));
                    
                    if ($itemId <= 0) continue;
                    
                    $orderItem = OrderItem::find($itemId);
                    if (!$orderItem || $orderItem->order_id !== $id) {
                        continue;
                    }
                    
                    $oldQty = (int)$orderItem->quantity;
                    
                    if ($quantity === 0) {
                        // Delete item
                        $itemsChanged = true;
                        $orderItem->delete();
                    } else if ($quantity !== $oldQty) {
                        // Update quantity
                        $itemsChanged = true;
                        $unitPrice = $orderItem->unit_price ?? 0;
                        $totalPrice = $unitPrice * $quantity;
                        $orderItem->update([
                            'quantity' => $quantity,
                            'total_price' => $totalPrice
                        ]);
                    }
                }
                
                // Add new items
                if (!empty($newItems)) {
                    $itemsChanged = true;
                    foreach ($newItems as $row) {
                        $type = $row['type'] ?? '';
                        $itemId = (int)($row['id'] ?? 0);
                        $quantity = max(1, (int)($row['quantity'] ?? 1));
                        
                        if ($itemId <= 0) continue;
                        
                        $itemName = '';
                        $unitPrice = 0;
                        
                        if ($type === 'product') {
                            $product = Product::findById($itemId);
                            if ($product && $product->is_active) {
                                $itemName = $product->name;
                                $unitPrice = $product->price;
                            } else {
                                continue;
                            }
                        } elseif ($type === 'combo_pack') {
                            $combo = ComboPack::findById($itemId);
                            if ($combo && $combo->is_active) {
                                $itemName = $combo->name;
                                $unitPrice = $combo->price;
                            } else {
                                continue;
                            }
                        } else {
                            continue;
                        }
                        
                        OrderItem::create([
                            'order_id' => $id,
                            'product_id' => $type === 'product' ? $itemId : null,
                            'combo_pack_id' => $type === 'combo_pack' ? $itemId : null,
                            'item_name' => $itemName,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_price' => $unitPrice * $quantity
                        ]);
                    }
                }
                
                if ($itemsChanged) {
                    $changes['items'] = true; // Mark that items changed
                }
            }
            
            // Recalculate order totals if discount or items changed
            if (isset($data['discount_amount']) || $itemsChanged) {
                $discountAmount = isset($data['discount_amount']) ? (float)$data['discount_amount'] : ($order->discount_amount ?? 0);
                $oldTotal = $order->total_amount;
                
                $this->recalculateOrderInternal($id, $discountAmount);
                
                // Refresh order to get new total
                $order = Order::findById($id);
                if ($order->total_amount != $oldTotal) {
                    $changes['total_amount'] = [
                        'old' => $oldTotal,
                        'new' => $order->total_amount
                    ];
                }
            }
            
            // Update order fields
            if (!empty($updateData)) {
                $order->update($updateData);
            }
            
            // Log changes
            if (!empty($changes)) {
                $changeSummary = json_encode($changes);
                OrderLog::log($id, 'order_modified', $changeSummary, null, $this->request->user['id'] ?? null);
            }
            
            $conn->commit();
            
            // Send email notification if changes were made and email is enabled
            if ($sendEmail && !empty($changes)) {
                $this->sendOrderModificationEmail($id, $oldOrderData, $changes);
            }
            
            // Get updated order with all relations
            $updatedOrder = Order::findById($id);
            $this->success($updatedOrder->toArray(), 'Order updated successfully');
        } catch (\Exception $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $this->error('Failed to update order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update order status
     * PUT /api/admin/orders/{id}/status
     */
    public function updateStatus(int $id): void {
        try {
            $order = Order::findById($id);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            $oldStatus = $order->order_status;
            $newStatus = $this->request->input('order_status');
            $paymentStatus = $this->request->input('payment_status');
            $sendEmail = (bool)($this->request->input('send_email_notification', true));
            
            $updateData = [];
            $changes = [];
            
            if ($newStatus && $newStatus !== $oldStatus) {
                $updateData['order_status'] = $newStatus;
                $changes['order_status'] = ['old' => $oldStatus, 'new' => $newStatus];
            }
            if ($paymentStatus && $paymentStatus !== $order->payment_status) {
                $updateData['payment_status'] = $paymentStatus;
                $changes['payment_status'] = ['old' => $order->payment_status, 'new' => $paymentStatus];
            }
            
            if (!empty($updateData)) {
                $order->update($updateData);
                
                // Log status change
                if (isset($changes['order_status'])) {
                    OrderLog::log($id, 'status_change', $oldStatus, $newStatus, $this->request->user['id'] ?? null);
                }
                
                // Send email notification if enabled
                if ($sendEmail && !empty($changes)) {
                    $oldOrderData = $order->toArray();
                    $oldOrderData['order_status'] = $oldStatus;
                    $this->sendOrderModificationEmail($id, $oldOrderData, $changes);
                }

                // Send WhatsApp Notification
                try {
                     // Check specific status settings
                     $waEnabled = false;
                     $newStatusKey = $newStatus ?? '';
                     if ($newStatusKey === 'shipped' && \App\Models\Setting::get('whatsapp_notify_shipping', '0') === '1') $waEnabled = true;
                     elseif ($newStatusKey === 'delivered' && \App\Models\Setting::get('whatsapp_notify_delivery', '0') === '1') $waEnabled = true;
                     elseif ($newStatusKey === 'cancelled') $waEnabled = true; // Always notify cancellation if enabled generally? Or maybe strictly control. For now, let's treat it as critical.
                     
                     if ($waEnabled && isset($changes['order_status'])) {
                        $waService = new \App\Services\WhatsAppService();
                        
                        // Fetch customer
                        $customer = \App\Models\Customer::findById($order->customer_id);
                        
                        if ($customer && !empty($customer->phone)) {
                            $orderInfo = $order->toArray();
                            $orderInfo['customer_name'] = $customer->name;
                            $orderInfo['customer_phone'] = $customer->phone;
                            
                            $waService->sendOrderStatusUpdate($orderInfo, $newStatus);
                        }
                     }
                } catch (\Exception $e) {
                    error_log("WhatsApp Status Update Error: " . $e->getMessage());
                }
            }
            
            $this->success($order->toArray(), 'Order status updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update order status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get order logs
     * GET /api/admin/orders/{id}/logs
     */
    public function getLogs(int $id): void {
        try {
            $order = Order::findById($id);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            $logs = OrderLog::getByOrderId($id);
            $this->success($logs, 'Order logs retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve order logs: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update order items (add/remove/update)
     * PUT /api/admin/orders/{id}/items
     */
    public function updateOrderItems(int $id): void {
        try {
            $order = Order::findById($id);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            $data = $this->request->getBody();
            $items = $data['items'] ?? [];
            $newItems = $data['new_items'] ?? [];
            $discountAmount = (float)($data['discount_amount'] ?? 0);
            
            $conn = Database::getConnection();
            $conn->beginTransaction();
            
            // Update existing items
            foreach ($items as $row) {
                $itemId = (int)($row['id'] ?? 0);
                $quantity = max(0, (int)($row['quantity'] ?? 0));
                
                if ($itemId <= 0) continue;
                
                $orderItem = OrderItem::find($itemId);
                if (!$orderItem || $orderItem->order_id !== $id) {
                    continue;
                }
                
                if ($quantity === 0) {
                    // Delete item
                    $orderItem->delete();
                } else {
                    // Update quantity
                    $unitPrice = $orderItem->unit_price ?? 0;
                    $totalPrice = $unitPrice * $quantity;
                    $orderItem->update([
                        'quantity' => $quantity,
                        'total_price' => $totalPrice
                    ]);
                }
            }
            
            // Add new items
            foreach ($newItems as $row) {
                $type = $row['type'] ?? '';
                $itemId = (int)($row['id'] ?? 0);
                $quantity = max(1, (int)($row['quantity'] ?? 1));
                
                if ($itemId <= 0) continue;
                
                $itemName = '';
                $unitPrice = 0;
                
                if ($type === 'product') {
                    $product = Product::findById($itemId);
                    if ($product && $product->is_active) {
                        $itemName = $product->name;
                        $unitPrice = $product->price;
                    } else {
                        continue;
                    }
                } elseif ($type === 'combo_pack') {
                    $combo = ComboPack::findById($itemId);
                    if ($combo && $combo->is_active) {
                        $itemName = $combo->name;
                        $unitPrice = $combo->price;
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
                
                // Create order item using Model
                OrderItem::create([
                    'order_id' => $id,
                    'product_id' => $type === 'product' ? $itemId : null,
                    'combo_pack_id' => $type === 'combo_pack' ? $itemId : null,
                    'item_name' => $itemName,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity
                ]);
            }
            
            // Recalculate order totals
            $this->recalculateOrderInternal($id, $discountAmount);
            
            $conn->commit();
            
            // Get updated order
            $updatedOrder = Order::findById($id);
            $this->success($updatedOrder->toArray(), 'Order items updated successfully');
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $this->error('Failed to update order items: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Recalculate order totals
     * POST /api/admin/orders/{id}/recalculate
     */
    public function recalculateOrder(int $id): void {
        try {
            $discountAmount = (float)($this->request->input('discount_amount', 0));
            $this->recalculateOrderInternal($id, $discountAmount);
            
            $order = Order::findById($id);
            $this->success($order->toArray(), 'Order recalculated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to recalculate order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Search items (products and combo packs) for order editing
     * GET /api/admin/orders/search-items
     */
    public function searchItems(): void {
        $query = trim($this->request->get('q', ''));
        
        if (empty($query)) {
            $this->success(['results' => []], 'No results');
            return;
        }
        
        try {
            $results = [];
            
            // Search products
            $products = Product::search($query, 10);
            foreach ($products as $product) {
                $results[] = [
                    'type' => 'product',
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => (float)($product['price'] ?? 0)
                ];
            }
            
            // Search combo packs
            $comboPacks = ComboPack::search($query, 10);
            foreach ($comboPacks as $combo) {
                $results[] = [
                    'type' => 'combo_pack',
                    'id' => $combo['id'],
                    'name' => $combo['name'],
                    'price' => (float)($combo['price'] ?? 0)
                ];
            }
            
            $this->success(['results' => $results], 'Search results retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to search items: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create new order
     * POST /api/admin/orders
     */
    public function store(): void {
        try {
            $data = $this->request->getBody();
            $customerDetails = $data['customer_details'] ?? [];
            $items = $data['items'] ?? [];
            $discountAmount = (float)($data['discount_amount'] ?? 0);
            
            // Validate required customer fields
            $requiredFields = ['first_name', 'email', 'phone', 'address', 'city', 'pincode'];
            foreach ($requiredFields as $field) {
                if (empty($customerDetails[$field])) {
                    $this->validationError([$field => ["Field {$field} is required"]], 'Validation failed');
                    return;
                }
            }
            
            if (empty($items)) {
                $this->validationError(['items' => ['Order items are required']], 'Validation failed');
                return;
            }
            
            $conn = Database::getConnection();
            $conn->beginTransaction();
            
            // Create or update customer
            $customerName = trim($customerDetails['first_name']) . ' ' . trim($customerDetails['last_name'] ?? '');
            $customer = Customer::findOrCreate([
                'name' => $customerName,
                'email' => trim($customerDetails['email']),
                'phone' => trim($customerDetails['phone']),
                'address' => trim($customerDetails['address']),
                'city' => trim($customerDetails['city']),
                'pincode' => trim($customerDetails['pincode'])
            ]);
            
            // Prepare order items
            $orderItems = [];
            foreach ($items as $item) {
                $type = $item['type'] ?? '';
                $itemId = (int)($item['id'] ?? 0);
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                
                if ($itemId <= 0) continue;
                
                $itemName = '';
                $unitPrice = 0;
                
                if ($type === 'product') {
                    $product = Product::findById($itemId);
                    if ($product && $product->is_active) {
                        $itemName = $product->name;
                        $unitPrice = $product->price;
                    } else {
                        continue;
                    }
                } elseif ($type === 'combo_pack') {
                    $combo = ComboPack::findById($itemId);
                    if ($combo && $combo->is_active) {
                        $itemName = $combo->name;
                        $unitPrice = $combo->price;
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
                
                $orderItems[] = [
                    'product_id' => $type === 'product' ? $itemId : null,
                    'combo_pack_id' => $type === 'combo_pack' ? $itemId : null,
                    'item_name' => $itemName,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity
                ];
            }
            
            if (empty($orderItems)) {
                $conn->rollBack();
                $this->validationError(['items' => ['No valid items found']], 'Validation failed');
                return;
            }
            
            // Create order
            $orderData = [
                'customer_id' => $customer->id,
                'order_number' => Order::generateOrderNumber(),
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => 'cash',
                'delivery_type' => 'standard',
                'delivery_charge' => 0,
                'order_source' => 'admin',
                'discount_amount' => $discountAmount
            ];
            
            $order = Order::createWithItems($orderData, $orderItems);
            
            $conn->commit();
            
            $this->success([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order' => $order->toArray()
            ], 'Order created successfully', 201);
        } catch (\Exception $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $this->error('Failed to create order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Send order modification email to customer
     */
    private function sendOrderModificationEmail(int $orderId, array $oldOrderData, array $changes): void {
        try {
            $order = Order::findById($orderId);
            if (!$order) {
                return;
            }
            
            $orderArray = $order->toArray();
            
            // Get customer email
            $conn = Database::getConnection();
            $stmt = $conn->prepare("SELECT email, name FROM customers WHERE id = ?");
            $stmt->execute([$order->customer_id]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$customer || empty($customer['email'])) {
                return;
            }
            
            // Get site/company info from settings
            $siteName = 'Sun Crackers';
            $companyName = 'Sun Crackers';
            $siteEmail = 'orders@suncrackers.in';
            
            if (class_exists('App\\Helpers\\SettingsHelper')) {
                $siteName = \App\Helpers\SettingsHelper::getSiteName();
                $companyName = \App\Helpers\SettingsHelper::getCompanyName();
                $siteEmail = \App\Helpers\SettingsHelper::getSiteEmail();
            } elseif (defined('SITE_NAME')) {
                $siteName = SITE_NAME;
                $companyName = defined('COMPANY_NAME') ? COMPANY_NAME : $siteName;
                $siteEmail = defined('SITE_EMAIL') ? SITE_EMAIL : (defined('FROM_EMAIL') ? FROM_EMAIL : 'orders@suncrackers.in');
            }
            
            // Format currency helper
            $formatCurrency = function($amount) {
                if (class_exists('App\\Helpers\\SettingsHelper')) {
                    $symbol = \App\Helpers\SettingsHelper::getCurrencySymbol();
                } else {
                    $symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
                }
                return $symbol . number_format((float)$amount, 2);
            };
            
            $subject = "Order Updated - " . $order->order_number;
            
            // Build changes list
            $changesList = "<ul style='list-style: none; padding: 0;'>";
            if (isset($changes['order_status'])) {
                $changesList .= "<li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Order Status:</strong> " . 
                    ucfirst($changes['order_status']['old']) . " → <span style='color: #FF7A00;'>" . 
                    ucfirst($changes['order_status']['new']) . "</span></li>";
            }
            if (isset($changes['payment_status'])) {
                $changesList .= "<li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Payment Status:</strong> " . 
                    ucfirst($changes['payment_status']['old']) . " → <span style='color: #FF7A00;'>" . 
                    ucfirst($changes['payment_status']['new']) . "</span></li>";
            }
            if (isset($changes['payment_method'])) {
                $changesList .= "<li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Payment Method:</strong> " . 
                    ucfirst($changes['payment_method']['old']) . " → <span style='color: #FF7A00;'>" . 
                    ucfirst($changes['payment_method']['new']) . "</span></li>";
            }
            if (isset($changes['discount_amount'])) {
                $changesList .= "<li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Discount:</strong> " . 
                    $formatCurrency($changes['discount_amount']['old']) . " → <span style='color: #FF7A00;'>" . 
                    $formatCurrency($changes['discount_amount']['new']) . "</span></li>";
            }
            if (isset($changes['total_amount'])) {
                $changesList .= "<li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Total Amount:</strong> " . 
                    $formatCurrency($changes['total_amount']['old']) . " → <span style='color: #FF7A00;'>" . 
                    $formatCurrency($changes['total_amount']['new']) . "</span></li>";
            }
            if (isset($changes['items'])) {
                $changesList .= "<li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Order Items:</strong> <span style='color: #FF7A00;'>Modified</span></li>";
            }
            $changesList .= "</ul>";
            
            // Get order items for display
            $itemsHtml = "";
            $items = OrderItem::getByOrderId($orderId);
            if (!empty($items)) {
                $itemsHtml = "<table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>";
                $itemsHtml .= "<thead><tr style='background: #f9f9f9;'><th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Item</th><th style='padding: 10px; text-align: right; border-bottom: 2px solid #ddd;'>Qty</th><th style='padding: 10px; text-align: right; border-bottom: 2px solid #ddd;'>Price</th><th style='padding: 10px; text-align: right; border-bottom: 2px solid #ddd;'>Total</th></tr></thead><tbody>";
                foreach ($items as $item) {
                    $itemsHtml .= "<tr><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($item['item_name']) . "</td>";
                    $itemsHtml .= "<td style='padding: 8px; text-align: right; border-bottom: 1px solid #eee;'>" . (int)$item['quantity'] . "</td>";
                    $itemsHtml .= "<td style='padding: 8px; text-align: right; border-bottom: 1px solid #eee;'>" . $formatCurrency($item['unit_price']) . "</td>";
                    $itemsHtml .= "<td style='padding: 8px; text-align: right; border-bottom: 1px solid #eee;'>" . $formatCurrency($item['total_price']) . "</td></tr>";
                }
                $itemsHtml .= "</tbody></table>";
            }
            
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .header { background: #0F9B9B; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .order-details { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                    .footer { background: #4B2E00; color: white; padding: 15px; text-align: center; font-size: 12px; }
                    .highlight { color: #FF7A00; font-weight: bold; }
                    .changes-box { background: #fff3cd; border-left: 4px solid #FF7A00; padding: 15px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>🎆 " . htmlspecialchars($siteName) . "</h1>
                    <h2>Order Update Notification</h2>
                </div>
                
                <div class='content'>
                    <p>Dear " . htmlspecialchars($customer['name']) . ",</p>
                    
                    <p>Your order <strong>" . htmlspecialchars($order->order_number) . "</strong> has been updated. Here are the details:</p>
                    
                    <div class='changes-box'>
                        <h3 style='margin-top: 0; color: #FF7A00;'>Changes Made:</h3>
                        " . $changesList . "
                    </div>
                    
                    <div class='order-details'>
                        <h3>Updated Order Details</h3>
                        <p><strong>Order Number:</strong> <span class='highlight'>" . htmlspecialchars($order->order_number) . "</span></p>
                        <p><strong>Order Date:</strong> " . date('d M Y, h:i A', strtotime($order->order_date ?? $order->created_at)) . "</p>
                        <p><strong>Order Status:</strong> <span class='highlight'>" . ucfirst($order->order_status) . "</span></p>
                        <p><strong>Payment Status:</strong> " . ucfirst($order->payment_status ?? 'pending') . "</p>
                        
                        <h4 style='margin-top: 20px; margin-bottom: 10px;'>Order Items:</h4>
                        " . $itemsHtml . "
                        
                        <div style='margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;'>
                            <p style='text-align: right; margin: 5px 0;'><strong>Subtotal:</strong> " . $formatCurrency($order->subtotal ?? 0) . "</p>";
            
            if (($order->discount_amount ?? 0) > 0) {
                $message .= "<p style='text-align: right; margin: 5px 0; color: green;'><strong>Discount:</strong> -" . $formatCurrency($order->discount_amount) . "</p>";
            }
            
            $message .= "<p style='text-align: right; margin: 5px 0; font-size: 18px;'><strong>Grand Total:</strong> <span class='highlight'>" . $formatCurrency($order->total_amount ?? 0) . "</span></p>
                        </div>
                    </div>
                    
                    <p>If you have any questions about these changes, please contact us at " . htmlspecialchars($siteEmail) . "</p>
                    
                    <p>Thank you for choosing " . htmlspecialchars($companyName) . "!</p>
                </div>
                
                <div class='footer'>
                    <p>" . htmlspecialchars($companyName) . "</p>
                    <p>For any queries, contact us at: " . htmlspecialchars($siteEmail) . "</p>
                </div>
            </body>
            </html>";
            
            // Send email using helper function
            require_once __DIR__ . '/../../../includes/helpers.php';
            sendEmailNotification($customer['email'], $subject, $message);
            
            logMessage("Order modification email sent to customer: " . $customer['email'] . " for order #" . $order->order_number, 'INFO');
        } catch (\Exception $e) {
            logMessage("Failed to send order modification email: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Internal method to recalculate order totals
     */
    private function recalculateOrderInternal(int $id, float $discountAmount = 0): void {
        $order = Order::findById($id);
        
        if (!$order) {
            throw new \Exception('Order not found');
        }
        
        // Get all order items
        $items = OrderItem::getByOrderId($id);
        
        // Calculate totals
        $subtotal = 0;
        $totalQuantity = 0;
        
        foreach ($items as $item) {
            $subtotal += (float)($item['total_price'] ?? 0);
            $totalQuantity += (int)($item['quantity'] ?? 0);
        }
        
        // Validate discount
        if ($discountAmount < 0) {
            $discountAmount = 0;
        }
        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }
        
        $totalAmount = $subtotal - $discountAmount;
        $avgUnitPrice = $totalQuantity > 0 ? ($subtotal / $totalQuantity) : 0;
        
        // Update order using Model
        $order->update([
            'quantity' => $totalQuantity,
            'unit_price' => $avgUnitPrice,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount
        ]);
    }
}



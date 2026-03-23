<?php

/**
 * POS Controller
 * Handles Point of Sale operations
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use App\Models\PendingPosOrder;

class PosController extends Controller {
    
    /**
     * Get POS data (products, categories)
     * GET /api/admin/pos/data
     */
    public function getData(): void {
        try {
            $categories = Category::getAll();
            $products = Product::getAll();
            
            // Group products by category
            $productsByCategory = [];
            foreach ($products as $product) {
                $catId = $product['category_id'] ?? 0;
                if (!isset($productsByCategory[$catId])) {
                    $productsByCategory[$catId] = [];
                }
                $productsByCategory[$catId][] = $product;
            }
            
            $this->success([
                'categories' => $categories,
                'products' => $products,
                'products_by_category' => $productsByCategory
            ], 'POS data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve POS data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Search customer for POS
     * GET /api/admin/pos/search-customer?q={query}
     */
    public function searchCustomer(): void {
        $query = $this->request->get('q', '');
        
        if (empty($query)) {
            $this->validationError(['q' => ['Search query is required']], 'Search query is required');
            return;
        }
        
        try {
            // Use Model method
            $customers = Customer::search($query, 10);
            
            $this->success($customers, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to search customers: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Process POS sale
     * POST /api/admin/pos/process-sale
     */
    public function processSale(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['customer']) || empty($data['items'])) {
                $this->validationError([
                    'customer' => ['Customer is required'],
                    'items' => ['Items are required']
                ], 'Validation failed');
                return;
            }
            
            // Find or create customer
            $customer = Customer::findOrCreate($data['customer']);
            
            // Verify customer was created/found and has an ID
            if (!$customer || !$customer->id) {
                $this->error('Failed to create or find customer', 500);
                return;
            }
            
            // Calculate totals
            $subtotal = 0;
            $items = [];
            foreach ($data['items'] as $item) {
                $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                $subtotal += $itemTotal;
                
                $items[] = [
                    'product_id' => $item['product_id'] ?? null,
                    'combo_pack_id' => $item['combo_pack_id'] ?? null,
                    'item_name' => $item['name'] ?? 'Product',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['price'] ?? 0,
                    'total_price' => $itemTotal
                ];
            }
            
            $discountAmount = (float)($data['discount'] ?? 0);
            $deliveryCharge = 0; // POS orders typically have no delivery charge
            $totalAmount = $subtotal - $discountAmount + $deliveryCharge;
            
            // Create order
            $orderData = [
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'delivery_type' => 'free',
                'delivery_charge' => $deliveryCharge,
                'payment_method' => ($data['payment_method'] ?? 'cash') === 'cash' ? 'cod' : 'online',
                'payment_status' => $data['payment_status'] ?? 'completed',
                'order_status' => 'confirmed',
                'order_source' => 'pos',
                'pos_location' => $data['pos_location'] ?? null
            ];
            
            $order = Order::createWithItems($orderData, $items);
            
            // Log order creation
            OrderLog::log($order->id, 'order_created', null, 'pos', $this->request->user['id'] ?? null);
            
            $orderArray = $order->toArray();
            // Add receipt URL for printing (Vue route)
            $orderArray['receipt_url'] = '/admin/pos/print-receipt?order_id=' . $order->id;
            
            $this->success($orderArray, 'Sale processed successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to process sale: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get pending POS orders
     * GET /api/admin/pos/pending-orders
     */
    public function getPendingOrders(): void {
        $sessionId = $this->request->get('session_id');
        $status = $this->request->get('status', 'draft');
        
        try {
            if ($sessionId) {
                $orders = PendingPosOrder::getBySessionId($sessionId);
            } else {
                $orders = PendingPosOrder::getByStatus($status);
            }
            
            $this->success($orders, 'Pending orders retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve pending orders: ' . $e->getMessage(), 500);
        }
    }
}



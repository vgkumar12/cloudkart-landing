<?php

/**
 * Wholesale POS Controller
 * Handles wholesale POS operations
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;

class PosWholesaleController extends Controller {
    
    /**
     * Get wholesale POS data (products with wholesale rates)
     * GET /api/admin/pos-wholesale/data
     */
    public function getData(): void {
        try {
            $categories = Category::getAll();
            
            // Get products with wholesale rates using Model
            $products = Product::getForWholesalePos();
            
            // Group by category
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
            ], 'Wholesale POS data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve wholesale POS data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Process wholesale sale
     * POST /api/admin/pos-wholesale/process-sale
     */
    public function processSale(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['customerPhone']) || empty($data['items'])) {
                $this->validationError([
                    'customerPhone' => ['Customer phone is required'],
                    'items' => ['Items are required']
                ], 'Validation failed');
                return;
            }
            
            // Find or create customer
            $customerData = [
                'name' => $data['customerName'] ?? 'Wholesale Customer',
                'email' => $data['customerEmail'] ?? "wholesale-{$data['customerPhone']}@suncrackers.in",
                'phone' => $data['customerPhone'],
                'address' => $data['customerAddress'] ?? 'Store',
                'city' => 'Store',
                'pincode' => '000000'
            ];
            
            $customer = Customer::findOrCreate($customerData);
            
            // Process items (carton-based)
            $subtotal = 0;
            $items = [];
            foreach ($data['items'] as $item) {
                $cartonQty = $item['carton_quantity'] ?? 1;
                $wholesaleRate = $item['wholesale_rate_per_carton'] ?? 0;
                $itemTotal = $cartonQty * $wholesaleRate;
                $subtotal += $itemTotal;
                
                $qtyPerCarton = $item['quantity_per_carton'] ?? 1;
                $totalPieces = $cartonQty * $qtyPerCarton;
                
                $items[] = [
                    'product_id' => $item['product_id'] ?? null,
                    'combo_pack_id' => null,
                    'item_name' => ($item['name'] ?? 'Product') . " ({$cartonQty} carton(s))",
                    'quantity' => $totalPieces, // Store total pieces
                    'unit_price' => $wholesaleRate / $qtyPerCarton, // Price per piece
                    'total_price' => $itemTotal
                ];
            }
            
            $discountAmount = (float)($data['discount'] ?? 0);
            $totalAmount = $subtotal - $discountAmount;
            
            // Create order
            $orderData = [
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'delivery_type' => 'free',
                'delivery_charge' => 0,
                'payment_method' => ($data['payment_method'] ?? 'cash') === 'cash' ? 'cod' : 'online',
                'payment_status' => $data['payment_status'] ?? 'completed',
                'order_status' => 'confirmed',
                'order_source' => 'wholesale',
                'pos_location' => $data['pos_location'] ?? null
            ];
            
            $order = Order::createWithItems($orderData, $items);
            
            // Log order creation
            OrderLog::log($order->id, 'order_created', null, 'wholesale', $this->request->user['id'] ?? null);
            
            $orderArray = $order->toArray();
            // Add receipt URL for printing (Vue route)
            $orderArray['receipt_url'] = '/admin/pos/print-receipt?order_id=' . $order->id;
            
            $this->success($orderArray, 'Wholesale sale processed successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to process wholesale sale: ' . $e->getMessage(), 500);
        }
    }
}


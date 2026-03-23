<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ComboPack;
use App\Models\ComboPackItem;
use App\Models\Product;
use App\Models\Category;

class PrintAdminController extends Controller {
    
    /**
     * Print order/invoice
     */
    public function order(): void {
        try {
            $orderId = (int)$this->request->get('id', 0);
            
            if ($orderId <= 0) {
                $this->validationError(['id' => ['Invalid order ID']], 'Validation failed');
                return;
            }
            
            // Get order details using Model
            $order = Order::getForPrint($orderId);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            // Get order items using Model
            $rawItems = OrderItem::getForPrint($orderId);
            
            // Merge duplicate lines
            $mergeMap = [];
            foreach ($rawItems as $it) {
                $pid = isset($it['product_id']) ? (int)$it['product_id'] : 0;
                $cid = isset($it['combo_pack_id']) ? (int)$it['combo_pack_id'] : 0;
                $key = $pid > 0 ? ('p:' . $pid) : ($cid > 0 ? ('c:' . $cid) : ('x:' . (int)$it['id']));
                
                if (!isset($mergeMap[$key])) {
                    $mergeMap[$key] = [
                        'item_name' => (string)$it['item_name'],
                        'quantity' => (int)$it['quantity'],
                        'unit_price' => (float)$it['unit_price'],
                        'total_price' => (float)$it['total_price'],
                    ];
                } else {
                    $mergeMap[$key]['quantity'] += (int)$it['quantity'];
                    $unit = (float)$mergeMap[$key]['unit_price'];
                    if ($unit <= 0) { 
                        $unit = (float)$it['unit_price']; 
                    }
                    $mergeMap[$key]['unit_price'] = $unit;
                    $mergeMap[$key]['total_price'] = $unit * (int)$mergeMap[$key]['quantity'];
                }
            }
            $orderItems = array_values($mergeMap);
            
            $this->success([
                'order' => $order,
                'items' => $orderItems
            ], 'Order data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve order: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Print POS receipt/invoice
     */
    public function posReceipt(): void {
        try {
            $orderId = (int)$this->request->get('order_id', 0);
            
            if ($orderId <= 0) {
                $this->validationError(['order_id' => ['Invalid order ID']], 'Validation failed');
                return;
            }
            
            // Get order details using Model
            $order = Order::getForPosReceipt($orderId);
            
            if (!$order) {
                $this->notFound('Order not found');
                return;
            }
            
            $isWholesale = ($order['order_source'] ?? '') === 'wholesale';
            
            // Get order items using Model
            $rawItems = OrderItem::getForPosReceipt($orderId);
            
            // Merge duplicate lines (with wholesale carton handling)
            $mergeMap = [];
            foreach ($rawItems as $it) {
                $pid = isset($it['product_id']) ? (int)$it['product_id'] : 0;
                $cid = isset($it['combo_pack_id']) ? (int)$it['combo_pack_id'] : 0;
                $key = $pid > 0 ? ('p:' . $pid) : ($cid > 0 ? ('c:' . $cid) : ('x:' . (int)$it['id']));
                
                $cartonQty = 0;
                $totalPieces = 0;
                $qtyPerCarton = (int)($it['quantity_per_carton'] ?? 1);
                
                if ($isWholesale && $pid > 0 && $qtyPerCarton > 0) {
                    $itemName = (string)$it['item_name'];
                    if (preg_match('/\((\d+)\s*carton/i', $itemName, $matches)) {
                        $cartonQty = (int)$matches[1];
                    } else {
                        $totalPieces = (int)$it['quantity'];
                        $cartonQty = $qtyPerCarton > 0 ? floor($totalPieces / $qtyPerCarton) : 0;
                    }
                    $totalPieces = $cartonQty * $qtyPerCarton;
                } else {
                    $totalPieces = (int)$it['quantity'];
                }
                
                if (!isset($mergeMap[$key])) {
                    $mergeMap[$key] = [
                        'item_name' => (string)$it['item_name'],
                        'quantity' => (int)$it['quantity'],
                        'unit_price' => (float)$it['unit_price'],
                        'total_price' => (float)$it['total_price'],
                        'product_id' => $pid,
                        'quantity_per_carton' => $qtyPerCarton,
                        'carton_quantity' => $cartonQty,
                        'total_pieces' => $totalPieces,
                    ];
                } else {
                    $mergeMap[$key]['quantity'] += (int)$it['quantity'];
                    if ($isWholesale && $pid > 0) {
                        $mergeMap[$key]['carton_quantity'] += $cartonQty;
                        $mergeMap[$key]['total_pieces'] += $totalPieces;
                    }
                    $unit = (float)$mergeMap[$key]['unit_price'];
                    if ($unit <= 0) { 
                        $unit = (float)$it['unit_price']; 
                    }
                    $mergeMap[$key]['unit_price'] = $unit;
                    $mergeMap[$key]['total_price'] = $unit * (int)$mergeMap[$key]['quantity'];
                }
            }
            $items = array_values($mergeMap);
            
            $this->success([
                'order' => $order,
                'items' => $items,
                'is_wholesale' => $isWholesale
            ], 'POS receipt data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve POS receipt: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Print combo pack details
     */
    public function comboPack(): void {
        try {
            $comboPackId = (int)$this->request->get('id', 0);
            
            if ($comboPackId <= 0) {
                $this->validationError(['id' => ['Invalid combo pack ID']], 'Validation failed');
                return;
            }
            
            // Get combo pack using Model
            $comboPack = ComboPack::getForPrint($comboPackId);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            // Get combo pack items using Model
            $comboItems = ComboPackItem::getByComboPackId($comboPackId);
            
            $this->success([
                'combo_pack' => $comboPack,
                'items' => $comboItems
            ], 'Combo pack data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve combo pack: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Print product price list
     */
    public function productPriceList(): void {
        try {
            // Get all products with categories using Model
            $products = Product::getAllWithCategories();
            
            // Get categories using Model
            $categories = Category::getAllActive();
            
            // Group products by category
            $grouped = [];
            $uncategorized = [];
            
            foreach ($products as $p) {
                if (!empty($p['category_id'])) {
                    $cid = (int)$p['category_id'];
                    if (!isset($grouped[$cid])) {
                        $grouped[$cid] = [];
                    }
                    $grouped[$cid][] = $p;
                } else {
                    $uncategorized[] = $p;
                }
            }
            
            $this->success([
                'categories' => $categories,
                'grouped_products' => $grouped,
                'uncategorized_products' => $uncategorized
            ], 'Price list data retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve price list: ' . $e->getMessage(), 500);
        }
    }
}



<?php

/**
 * Order Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Models\Product;
use PDO;

class Order extends Model {
    protected string $table = 'orders';
    
    public ?int $id = null;
    public ?string $order_number = null;
    public ?int $customer_id = null;
    public ?int $combo_pack_id = null;
    public ?int $quantity = null;
    public ?float $unit_price = null;
    public ?float $subtotal = null;
    public ?float $discount_amount = null;
    public ?float $total_amount = null;
    public ?string $delivery_type = null;
    public ?float $delivery_charge = null;
    public ?string $payment_method = null;
    public ?string $payment_status = null;
    public ?string $order_status = null;
    public ?string $order_source = null;
    public ?string $pos_location = null;
    public ?string $preferred_delivery_date = null;
    public ?string $special_instructions = null;
    public ?string $order_date = null;
    public ?string $updated_at = null;
    
    // Customer fields from JOINs (not stored in orders table)
    public ?string $customer_name = null;
    public ?string $customer_email = null;
    public ?string $customer_phone = null;
    public ?string $customer_address = null;
    public ?string $customer_city = null;
    public ?string $customer_state = null;
    public ?string $customer_pincode = null;
    
    // Dynamic fields for display
    public ?int $item_count = null;
    public ?string $items_summary = null;
    public array $items = [];
    
    /**
     * Generate order number
     */
    public static function generateOrderNumber(): string {
        // Get order prefix from settings with fallback
        $prefix = 'SC';
        if (class_exists('App\\Helpers\\SettingsHelper')) {
            $prefix = \App\Helpers\SettingsHelper::getOrderPrefix();
        } elseif (defined('ORDER_PREFIX')) {
            $prefix = ORDER_PREFIX;
        }
        
        return $prefix . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get all orders
     */
    public static function getAll(?int $customerId = null, ?string $status = null, int $limit = 50): array {
        $conn = Database::getConnection();
        
        // Include item count and summary in the query
        $sql = "SELECT o.*, 
                       c.name as customer_name, 
                       c.email as customer_email, 
                       c.phone as customer_phone,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
                       (SELECT GROUP_CONCAT(COALESCE(item_name, 'Item') SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id LIMIT 3) as items_summary
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE 1=1";
        $params = [];
        
        if ($customerId) {
            $sql .= " AND o.customer_id = ?";
            $params[] = $customerId;
        }
        
        if ($status) {
            $sql .= " AND o.order_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.order_date DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            // Manually inject calculated fields since they aren't in Model properties by default
            // or rely on dynamic property assignment if Model supports it (it usually does via constructor)
            $order = new self($row);
            // Dynamic properties for display
            $order->item_count = $row['item_count']; 
            $order->items_summary = $row['items_summary'];
            return $order->toArray();
        }, $results);
    }
    
    /**
     * Search orders with pagination and filters
     */
    public static function searchWithPagination(?string $search = null, ?string $status = null, ?string $paymentStatus = null, ?string $orderSource = null, int $page = 1, int $limit = 20, ?string $sortBy = null, ?string $sortOrder = null): array {
        $conn = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($status) {
            $whereConditions[] = "o.order_status = ?";
            $params[] = $status;
        }
        
        if ($paymentStatus) {
            $whereConditions[] = "o.payment_status = ?";
            $params[] = $paymentStatus;
        }
        
        if ($orderSource) {
            $whereConditions[] = "o.order_source = ?";
            $params[] = $orderSource;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE {$whereClause}";
        $countParams = [];
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        if ($status) {
            $countParams[] = $status;
        }
        if ($paymentStatus) {
            $countParams[] = $paymentStatus;
        }
        if ($orderSource) {
            $countParams[] = $orderSource;
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        // Validate and set sort parameters
        $allowedSortColumns = ['id', 'order_number', 'order_date', 'order_status', 'payment_status', 'total_amount', 'customer_id', 'created_at', 'updated_at'];
        $sortColumn = 'id'; // Default
        $sortDirection = 'DESC'; // Default
        
        if ($sortBy && in_array($sortBy, $allowedSortColumns)) {
            $sortColumn = $sortBy;
        }
        
        if ($sortOrder && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($sortOrder);
        }
        
        // Get orders with customer info
        $sql = "SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE {$whereClause}
                ORDER BY o.{$sortColumn} {$sortDirection}
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $orders = array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
        
        return [
            'orders' => $orders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Find order by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                                c.address as customer_address, c.city as customer_city, c.state as customer_state, c.pincode as customer_pincode
                                FROM orders o
                                LEFT JOIN customers c ON o.customer_id = c.id
                                WHERE o.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find order by order number
     */
    public static function findByOrderNumber(string $orderNumber): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get order items
     */
    public function getItems(): array {
        if (!$this->id) {
            return [];
        }
        
        return OrderItem::getByOrderId($this->id);
    }
    
    /**
     * Create order with items
     */
    public static function createWithItems(array $orderData, array $items): self {
        $conn = Database::getConnection();
        $conn->beginTransaction();
        
        try {
            error_log("Order::createWithItems - Start. Items count: " . count($items));
            
            // Generate order number if not provided
            if (empty($orderData['order_number'])) {
                $orderData['order_number'] = self::generateOrderNumber();
            }
            
            error_log("Order::createWithItems - Creating Order");
            // Create order
            $order = self::create($orderData);
            error_log("Order::createWithItems - Order Created ID: " . $order->id);
            
            // Create order items and reduce stock
            foreach ($items as $item) {
                $item['order_id'] = $order->id;
                error_log("Order::createWithItems - Creating Item: " . json_encode($item));
                // Create order item
                // Use default create but without try-catch to let errors bubble up
                OrderItem::create($item);
                
                // Reduce stock for products (not combo packs)
                if (!empty($item['product_id'])) {
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        // Quantity in order_item is already in pieces for both retail and wholesale
                        // (For wholesale, PosWholesaleController converts cartons to total pieces)
                        $quantityToReduce = (int)($item['quantity'] ?? 1);
                        $product->reduceStock($quantityToReduce);
                    }
                }
            }
            
            // Verify items were actually created in the DB
            $createdItems = OrderItem::getByOrderId($order->id);
            if (count($createdItems) !== count($items)) {
                // Determine why it failed
                $missingCount = count($items) - count($createdItems);
                throw new \Exception("Sanity check failed: Order created but $missingCount items failed to persist within transaction. Input: " . count($items) . ", Saved: " . count($createdItems));
            }

            $conn->commit();
            error_log("Order::createWithItems - Transaction Committed");
            return $order;
        } catch (\Exception $e) {
            $conn->rollBack();
            error_log("Order::createWithItems - Failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Recalculate order totals
     */
    public function recalculateOrder(): void {
        if (!$this->id) {
            return;
        }
        
        $items = OrderItem::getByOrderId($this->id);
        
        $subtotal = 0;
        $totalQuantity = 0;
        
        foreach ($items as $item) {
            $subtotal += (float)($item['total_price'] ?? 0);
            $totalQuantity += (int)($item['quantity'] ?? 0);
        }
        
        $discountAmount = (float)($this->discount_amount ?? 0);
        if ($discountAmount < 0) {
            $discountAmount = 0;
        }
        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }
        
        $totalAmount = $subtotal - $discountAmount;
        $avgUnitPrice = $totalQuantity > 0 ? ($subtotal / $totalQuantity) : 0;
        
        $this->update([
            'quantity' => $totalQuantity,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'unit_price' => $avgUnitPrice
        ]);
    }
    
    /**
     * Get order statistics
     */
    public static function getStats(): array {
        $conn = Database::getConnection();
        
        $totalOrdersStmt = $conn->query("SELECT COUNT(*) FROM orders");
        $totalOrders = (int)$totalOrdersStmt->fetchColumn();
        
        $totalSalesStmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'completed'");
        $totalSales = (float)$totalSalesStmt->fetchColumn();
        
        $pendingOrdersStmt = $conn->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('pending', 'confirmed')");
        $pendingOrders = (int)$pendingOrdersStmt->fetchColumn();
        
        return [
            'total_orders' => $totalOrders,
            'total_sales' => $totalSales,
            'pending_orders' => $pendingOrders
        ];
    }
    
    /**
     * Get recent orders
     */
    public static function getRecentOrders(int $limit = 10): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT o.*, c.name as customer_name, c.phone as customer_phone,
                   COUNT(oi.id) as item_count,
                   GROUP_CONCAT(oi.item_name SEPARATOR ', ') as items_summary
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            GROUP BY o.id
            ORDER BY o.order_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get order with customer details for printing
     */
    public static function getForPrint(int $orderId): ?array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT o.*, c.name as customer_name, c.email as customer_email, 
                   c.phone as customer_phone, c.address as customer_address, 
                   c.city as customer_city, c.pincode as customer_pincode 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get order for POS receipt
     */
    public static function getForPosReceipt(int $orderId): ?array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ? AND (o.order_source = 'pos' OR o.order_source = 'wholesale')
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get dashboard stats (extended)
     */
    public static function getDashboardStats(): array {
        $conn = Database::getConnection();
        
        return [
            'total_orders' => (int)$conn->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'pending_orders' => (int)$conn->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn(),
            'total_revenue' => (float)$conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'completed'")->fetchColumn(),
            'today_orders' => (int)$conn->query("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()")->fetchColumn(),
            'today_revenue' => (float)$conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(order_date) = CURDATE() AND payment_status = 'completed'")->fetchColumn(),
        ];
    }
    
    /**
     * Get sales report by date range
     */
    public static function getSalesReport(?string $startDate = null, ?string $endDate = null): array {
        $conn = Database::getConnection();
        
        $sql = "SELECT 
                    DATE(order_date) as date,
                    COUNT(*) as order_count,
                    SUM(total_amount) as revenue,
                    AVG(total_amount) as avg_order_value
                FROM orders
                WHERE payment_status = 'completed'";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND DATE(order_date) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(order_date) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY DATE(order_date) ORDER BY date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['quantity'] = (int)($array['quantity'] ?? 0);
        $array['subtotal'] = $array['subtotal'] ? (float)$array['subtotal'] : null;
        $array['discount_amount'] = $array['discount_amount'] ? (float)$array['discount_amount'] : null;
        $array['total_amount'] = $array['total_amount'] ? (float)$array['total_amount'] : null;
        $array['delivery_charge'] = $array['delivery_charge'] ? (float)$array['delivery_charge'] : null;
        
        // Customer fields are now properties, so they'll be included via parent::toArray()
        
        // Include items if order is loaded
        if ($this->id) {
            $array['items'] = $this->getItems();
        }
        
        return $array;
    }

    /**
     * Update payment gateway order ID
     */
    public function updatePaymentGatewayOrderId(int $orderId, string $gatewayOrderId): bool {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("UPDATE orders SET payment_gateway_order_id = ? WHERE id = ?");
        return $stmt->execute([$gatewayOrderId, $orderId]);
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus(int $orderId, string $paymentMethod, string $paymentStatus, ?string $gatewayOrderId = null, ?array $gatewayResponse = null): bool {
        $conn = Database::getConnection();
        
        $sql = "UPDATE orders SET 
                payment_method = ?, 
                payment_status = ?";
        
        $params = [$paymentMethod, $paymentStatus];
        
        if ($gatewayOrderId) {
            $sql .= ", payment_gateway_order_id = ?";
            $params[] = $gatewayOrderId;
        }
        
        if ($gatewayResponse) {
            $sql .= ", payment_gateway_response = ?";
            $params[] = json_encode($gatewayResponse);
        }
        
        if ($paymentStatus === 'completed') {
            $sql .= ", payment_completed_at = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $orderId;
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    }
}


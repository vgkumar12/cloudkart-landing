<?php

/**
 * Order Item Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class OrderItem extends Model {
    protected string $table = 'order_items';
    
    public ?int $id = null;
    public ?int $order_id = null;
    public ?int $product_id = null;
    public ?int $combo_pack_id = null;
    public ?string $item_name = null;
    public ?int $quantity = null;
    public ?float $unit_price = null;
    public ?float $total_price = null;
    public ?string $created_at = null;
    
    protected array $fillable = [
        'order_id', 
        'product_id', 
        'combo_pack_id', 
        'item_name', 
        'quantity', 
        'unit_price', 
        'total_price'
    ];
    
    /**
     * Get items by order ID
     */
    public static function getByOrderId(int $orderId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
        $stmt->execute([$orderId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Get order items for printing
     */
    public static function getForPrint(int $orderId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT id, product_id, combo_pack_id, item_name, quantity, unit_price, total_price 
            FROM order_items 
            WHERE order_id = ? 
            ORDER BY id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get order items for POS receipt (with product carton info)
     */
    public static function getForPosReceipt(int $orderId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT oi.*, p.quantity_per_carton 
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['quantity'] = (int)($array['quantity'] ?? 0);
        $array['unit_price'] = $array['unit_price'] ? (float)$array['unit_price'] : null;
        $array['total_price'] = $array['total_price'] ? (float)$array['total_price'] : null;
        return $array;
    }
}


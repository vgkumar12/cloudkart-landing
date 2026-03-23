<?php

/**
 * Order Log Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class OrderLog extends Model {
    protected string $table = 'order_logs';
    
    public ?int $id = null;
    public ?int $order_id = null;
    public ?string $action = null;
    public ?string $old_value = null;
    public ?string $new_value = null;
    public ?int $user_id = null;
    public ?string $created_at = null;
    
    /**
     * Get recent activity
     */
    public static function getRecentActivity(int $limit = 10): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT ol.*, o.order_number, c.name as customer_name
            FROM order_logs ol
            JOIN orders o ON ol.order_id = o.id
            JOIN customers c ON o.customer_id = c.id
            ORDER BY ol.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get logs by order ID
     */
    public static function getByOrderId(int $orderId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT ol.*
            FROM order_logs ol
            WHERE ol.order_id = ?
            ORDER BY ol.created_at DESC
        ");
        $stmt->execute([$orderId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Log an order action
     * @param int $orderId Order ID
     * @param string $action Action name (e.g., 'order_created', 'status_change')
     * @param mixed $oldValue Old value or first context parameter
     * @param mixed $newValue New value or second context parameter (for order_created, this is the source like 'pos' or 'wholesale')
     * @param int|null $userId User ID who performed the action
     */
    public static function log(int $orderId, string $action, $oldValue = null, $newValue = null, ?int $userId = null): void {
        // Convert values to strings for storage
        $oldValueStr = $oldValue !== null ? (string)$oldValue : null;
        $newValueStr = $newValue !== null ? (string)$newValue : null;
        
        self::create([
            'order_id' => $orderId,
            'action' => $action,
            'old_value' => $oldValueStr,
            'new_value' => $newValueStr,
            'user_id' => $userId
        ]);
    }
    
    public function toArray(): array {
        return parent::toArray();
    }
}

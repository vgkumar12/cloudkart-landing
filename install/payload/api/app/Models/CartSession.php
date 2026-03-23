<?php

/**
 * Cart Session Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class CartSession extends Model {
    protected string $table = 'cart_sessions';
    
    public ?int $id = null;
    public ?string $session_id = null;
    public ?int $user_id = null;
    public ?int $total_items = null;
    public ?float $total_amount = null;
    public ?float $shipping_cost = null;
    public ?float $tax_amount = null;
    public ?float $discount_amount = null;
    public ?float $final_amount = null;
    public ?string $coupon_code = null;
    public ?string $shipping_address = null;
    public ?string $billing_address = null;
    public ?string $payment_method = null;
    public ?string $notes = null;
    public ?string $expires_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Find by session ID
     */
    public static function findBySessionId(string $sessionId): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM cart_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find or create by session ID
     */
    public static function findOrCreate(string $sessionId, ?int $userId = null): self {
        $session = self::findBySessionId($sessionId);
        
        if ($session) {
            if ($userId && !$session->user_id) {
                $session->update(['user_id' => $userId]);
            }
            return $session;
        }
        
        return self::create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'total_items' => 0,
            'total_amount' => 0,
            'final_amount' => 0
        ]);
    }
    
    /**
     * Get cart session with customer and items
     */
    public static function getWithDetails(string $sessionId): ?array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT cs.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
            FROM cart_sessions cs
            LEFT JOIN customers c ON cs.user_id = c.id
            WHERE cs.session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return null;
        }
        
        // Get cart items
        $itemsStmt = $conn->prepare("
            SELECT 
                c.*,
                p.name as product_name,
                cp.name as combo_pack_name,
                COALESCE(p.cost_price, 0) as cost_price
            FROM cart c
            LEFT JOIN products p ON c.product_id = p.id
            LEFT JOIN combo_packs cp ON c.combo_pack_id = cp.id
            WHERE c.session_id = ?
            ORDER BY c.created_at ASC
        ");
        $itemsStmt->execute([$sessionId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'session' => $session,
            'items' => $items
        ];
    }
    
    /**
     * Get all cart sessions with statistics
     */
    public static function getAllWithStats(int $limit = 100): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT 
                cs.*,
                c.name as customer_name,
                c.email as customer_email,
                COALESCE(SUM(COALESCE(p.cost_price, 0) * cart_items.quantity), 0) as total_cost,
                COALESCE(SUM(cart_items.total_price), 0) - COALESCE(SUM(COALESCE(p.cost_price, 0) * cart_items.quantity), 0) as profit
            FROM cart_sessions cs
            LEFT JOIN customers c ON cs.user_id = c.id
            LEFT JOIN cart cart_items ON cs.session_id = cart_items.session_id
            LEFT JOIN products p ON cart_items.product_id = p.id
            GROUP BY cs.id
            ORDER BY cs.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        
        // Parse JSON fields
        if (isset($array['shipping_address']) && is_string($array['shipping_address'])) {
            $array['shipping_address'] = json_decode($array['shipping_address'], true) ?? [];
        }
        if (isset($array['billing_address']) && is_string($array['billing_address'])) {
            $array['billing_address'] = json_decode($array['billing_address'], true) ?? [];
        }
        
        // Type casting
        $array['total_items'] = (int)($array['total_items'] ?? 0);
        $array['total_amount'] = $array['total_amount'] ? (float)$array['total_amount'] : 0;
        $array['shipping_cost'] = $array['shipping_cost'] ? (float)$array['shipping_cost'] : 0;
        $array['tax_amount'] = $array['tax_amount'] ? (float)$array['tax_amount'] : 0;
        $array['discount_amount'] = $array['discount_amount'] ? (float)$array['discount_amount'] : 0;
        $array['final_amount'] = $array['final_amount'] ? (float)$array['final_amount'] : 0;
        
        return $array;
    }
}


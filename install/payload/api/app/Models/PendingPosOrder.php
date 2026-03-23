<?php

/**
 * Pending POS Order Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class PendingPosOrder extends Model {
    protected string $table = 'pending_pos_orders';
    
    public ?int $id = null;
    public ?string $session_id = null;
    public ?int $staff_user_id = null;
    public ?string $customer_phone = null;
    public ?string $items = null;
    public ?float $total_amount = null;
    public ?string $payment_method = null;
    public ?string $status = null;
    public ?string $notes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get by session ID
     */
    public static function getBySessionId(string $sessionId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM pending_pos_orders WHERE session_id = ? ORDER BY created_at DESC");
        $stmt->execute([$sessionId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Get by status
     */
    public static function getByStatus(string $status): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM pending_pos_orders WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        
        // Parse JSON items
        if (isset($array['items']) && is_string($array['items'])) {
            $array['items'] = json_decode($array['items'], true) ?? [];
        }
        
        $array['total_amount'] = $array['total_amount'] ? (float)$array['total_amount'] : 0;
        
        return $array;
    }
}




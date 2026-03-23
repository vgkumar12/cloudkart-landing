<?php

/**
 * Cart Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Cart extends Model {
    protected string $table = 'cart';
    
    public ?int $id = null;
    public ?string $session_id = null;
    public ?int $user_id = null;
    public ?int $product_id = null;
    public ?int $variant_id = null;
    public ?int $combo_pack_id = null;
    public ?int $quantity = null;
    public ?float $price = null;
    public ?float $total_price = null;
    public ?string $item_type = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get cart items by session ID or user ID
     * If user_id is provided, fetch by user_id (prioritize logged-in users)
     * Otherwise, fetch by session_id
     */
    public static function getItems(?string $sessionId = null, ?int $userId = null): array {
        $conn = Database::getConnection();
        $sql = "SELECT 
                    c.*,
                    p.name as product_name,
                    p.price as product_list_price,
                    p.image_path as product_image_path,
                    p.thumb_path as product_thumb_path,
                    p.sale_price as product_sale_price,
                    pv.sku as variant_sku,
                    pv.price as variant_price,
                    pv.stock_quantity as variant_stock,
                    (SELECT GROUP_CONCAT(CONCAT(a.name, ': ', av.value) SEPARATOR ', ')
                     FROM product_variant_attributes pva
                     JOIN attribute_values av ON pva.attribute_value_id = av.id
                     JOIN attributes a ON av.attribute_id = a.id
                     WHERE pva.variant_id = c.variant_id) as variant_attributes,
                    cp.name as combo_pack_name,
                    cp.image_path as combo_pack_image_path,
                    cp.thumb_path as combo_pack_thumb_path,
                    cp.price as combo_pack_price
                FROM cart c
                LEFT JOIN products p ON c.product_id = p.id
                LEFT JOIN product_variants pv ON c.variant_id = pv.id
                LEFT JOIN combo_packs cp ON c.combo_pack_id = cp.id
                WHERE 1=1";
        $params = [];
        
        $whereClauses = [];
        
        if ($userId) {
            $whereClauses[] = "c.user_id = ?";
            $params[] = $userId;
        }
        
        if ($sessionId) {
            $whereClauses[] = "c.session_id = ?";
            $params[] = $sessionId;
        }
        
        if (empty($whereClauses)) {
            return [];
        }
        
        $sql .= " AND (" . implode(' OR ', $whereClauses) . ")";
        
        error_log("Cart::getItems - Fetching with strategy: " . implode(' OR ', $whereClauses) . " | Params: " . json_encode($params));
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            $item = (new self($row))->toArray();
            
            // Add product/combo pack names
            $item['product_name'] = $row['product_name'] ?? null;
            $item['combo_pack_name'] = $row['combo_pack_name'] ?? null;
            $item['name'] = $item['product_name'] ?? $item['combo_pack_name'] ?? null;
            
            // Handle variant details if present
            if ($item['variant_id']) {
                $item['variant_sku'] = $row['variant_sku'] ?? null;
                $item['variant_attributes'] = $row['variant_attributes'] ?? '';
                // Ensure unit_price is correct for the variant if set
                if (isset($row['variant_price']) && $row['variant_price'] > 0) {
                    $item['price'] = (float)$row['variant_price'];
                }
            }

            // Add image paths
            $item['image_path'] = $row['product_image_path'] ?? $row['combo_pack_image_path'] ?? null;
            $item['thumb'] = $row['product_thumb_path'] ?? $row['combo_pack_thumb_path'] ?? $item['image_path'];
            
            // Add unit price (sale_price or price, prioritizing sale_price)
            if ($item['product_id']) {
                // If variant has its own price, use it. Otherwise use product's sale_price or price.
                $item['unit_price'] = ($item['variant_id'] && isset($row['variant_price']) && $row['variant_price'] > 0) 
                    ? (float)$row['variant_price'] 
                    : ($row['product_sale_price'] ?? $row['product_list_price'] ?? $item['price'] ?? null);
                
                $item['list_price'] = $row['product_list_price'] ?? null;
            } else {
                $item['unit_price'] = $row['combo_pack_price'] ?? $item['price'] ?? null;
                $item['list_price'] = $row['combo_pack_price'] ?? null;
            }
            
            // Recalculate total_price for consistency (fixes items with wrong stored price)
            $item['total_price'] = (float)$item['unit_price'] * (int)$item['quantity'];
            
            return $item;
        }, $results);
    }
    
    /**
     * Get cart summary (total items, total amount)
     */
    public static function getSummary(?string $sessionId = null, ?int $userId = null): array {
        $items = self::getItems($sessionId, $userId);
        
        $totalItems = 0;
        $totalAmount = 0.0;
        
        foreach ($items as $item) {
            $totalItems += (int)($item['quantity'] ?? 0);
            $totalAmount += (float)($item['total_price'] ?? 0);
        }
        
        return [
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'items' => $items
        ];
    }
    
    /**
     * Add item to cart
     */
    public static function addItem(array $data): self {
        // Check if item already exists
        $conn = Database::getConnection();
        $sql = "SELECT * FROM cart WHERE session_id = ? AND product_id <=> ? AND variant_id <=> ? AND combo_pack_id <=> ? AND item_type = ?";
        $params = [
            $data['session_id'] ?? '',
            $data['product_id'] ?? null,
            $data['variant_id'] ?? null,
            $data['combo_pack_id'] ?? null,
            $data['item_type'] ?? 'product'
        ];
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update quantity
            $newQuantity = ($existing['quantity'] ?? 0) + ($data['quantity'] ?? 1);
            $newTotal = $newQuantity * ($data['price'] ?? $existing['price']);
            
            $updateStmt = $conn->prepare("UPDATE cart SET quantity = ?, total_price = ? WHERE id = ?");
            $updateStmt->execute([$newQuantity, $newTotal, $existing['id']]);
            
            return self::findById($existing['id']);
        }
        
        // Create new item
        return self::create($data);
    }
    
    /**
     * Clear cart
     */
    public static function clear(?string $sessionId = null, ?int $userId = null): bool {
        error_log("Cart::clear - Start. session_id: " . ($sessionId ?? 'null') . ", user_id: " . ($userId ?? 'null'));
        $conn = Database::getConnection();
        $sql = "DELETE FROM cart WHERE 1=1";
        $params = [];
        
        if ($userId) {
            $whereClauses[] = "user_id = ?";
            $params[] = $userId;
        }
        
        if ($sessionId) {
            $whereClauses[] = "session_id = ?";
            $params[] = $sessionId;
        }
        
        if (empty($whereClauses)) {
            // Safety: Don't delete everything if no ID provided
            return false;
        }
        
        $sql .= " AND (" . implode(' OR ', $whereClauses) . ")";
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Find cart item by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM cart WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['quantity'] = (int)($array['quantity'] ?? 0);
        $array['price'] = $array['price'] ? (float)$array['price'] : null;
        $array['total_price'] = $array['total_price'] ? (float)$array['total_price'] : null;
        return $array;
    }
    
    /**
     * Migrate guest cart items to user
     * Updates session_id and user_id for items belonging to the old session
     * @return int Number of items migrated
     */
    public static function migrateGuestCartToUser(string $oldSessionId, int $userId): int {
        // Remove try-catch to allow AuthController to handle and report errors
        $conn = Database::getConnection();
        $newSessionId = session_id(); 
        
        error_log("Cart::migrateGuestCartToUser - Migrating from $oldSessionId to User $userId");
        
        // 1. Get all items from the guest session
        $sql = "SELECT * FROM cart WHERE session_id = ? AND (user_id IS NULL OR user_id = 0)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$oldSessionId]);
        $guestItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($guestItems)) {
            return 0;
        }
        
        $migratedCount = 0;
        
        foreach ($guestItems as $guestItem) {
            // 2. Check if user already has this specific item (product or combo)
            // We verify against the NEW session ID if possible, but mainly user_id
            $checkSql = "SELECT id, quantity FROM cart WHERE user_id = ? AND item_type = ? AND product_id <=> ? AND combo_pack_id <=> ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([
                $userId,
                $guestItem['item_type'],
                $guestItem['product_id'],
                $guestItem['combo_pack_id']
            ]);
            $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingItem) {
                // 3a. Merge: Update quantity of existing user item
                $newQuantity = $existingItem['quantity'] + $guestItem['quantity'];
                // Calculate expected total price based on unit price (approximate, since prices might change)
                // Better to just update quantity and let price be recalculated if needed, 
                // or sum the totals if we trust them. Let's sum totals for safety.
                $newTotal = ($existingItem['total_price'] ?? 0) + ($guestItem['total_price'] ?? 0); // fallback calc if needed
                
                $updateSql = "UPDATE cart SET quantity = ?, total_price = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([$newQuantity, $newTotal, $existingItem['id']]);
                
                // Delete the guest item as it's now merged
                $delSql = "DELETE FROM cart WHERE id = ?";
                $delStmt = $conn->prepare($delSql);
                $delStmt->execute([$guestItem['id']]);
                
            } else {
                // 3b. Transfer: Update the guest item to belong to the user
                // CRITICAL CHANGE: We PRESERVE the `session_id` (the UUID) so that the item keeps its trace.
                // The `user_id` is what matters for ownership now.
                // This also creates a fallback: if we query by UUID, we can find it (though we usually filter by user_id)
                $moveSql = "UPDATE cart SET user_id = ? WHERE id = ?";
                $moveStmt = $conn->prepare($moveSql);
                $moveStmt->execute([$userId, $guestItem['id']]);
            }
            $migratedCount++;
        }
        
        return $migratedCount;
    }
}


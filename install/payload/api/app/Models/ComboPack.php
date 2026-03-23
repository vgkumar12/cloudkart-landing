<?php

/**
 * Combo Pack Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class ComboPack extends Model {
    protected string $table = 'combo_packs';
    
    public ?int $id = null;
    public ?string $pack_key = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?float $price = null;
    public ?string $image_path = null;
    public ?string $thumb_path = null;
    public ?string $youtube_url = null;
    public ?bool $is_active = null;
    public ?int $display_order = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all active combo packs
     */
    public static function getAll(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM combo_packs WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find combo pack by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM combo_packs WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find combo pack by pack_key
     */
    public static function findByPackKey(string $packKey): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM combo_packs WHERE pack_key = ? AND is_active = 1");
        $stmt->execute([$packKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Search combo packs
     */
    public static function search(string $query, int $limit = 50): array {
        $conn = Database::getConnection();
        $searchTerm = "%{$query}%";
        $stmt = $conn->prepare("SELECT * FROM combo_packs WHERE is_active = 1 AND (name LIKE ? OR pack_key LIKE ? OR description LIKE ?) ORDER BY display_order ASC, id ASC LIMIT ?");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Get all combo packs including inactive
     */
    public static function getAllWithInactive(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM combo_packs ORDER BY display_order ASC, id ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Search combo packs with pagination and filters
     */
    public static function searchWithPagination(?string $search = null, int $page = 1, int $limit = 20, ?string $sortBy = null, ?string $sortOrder = null, ?bool $includeInactive = false): array {
        $conn = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(name LIKE ? OR pack_key LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!$includeInactive) {
            $whereConditions[] = "is_active = 1";
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM combo_packs WHERE {$whereClause}";
        $countParams = [];
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        // Validate and set sort parameters
        $allowedSortColumns = ['id', 'name', 'pack_key', 'price', 'display_order', 'is_active', 'created_at', 'updated_at'];
        $sortColumn = 'id'; // Default
        $sortDirection = 'DESC'; // Default
        
        if ($sortBy && in_array($sortBy, $allowedSortColumns)) {
            $sortColumn = $sortBy;
        }
        
        if ($sortOrder && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($sortOrder);
        }
        
        // Get combo packs
        $sql = "SELECT * FROM combo_packs 
                WHERE {$whereClause}
                ORDER BY {$sortColumn} {$sortDirection}
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $comboPacks = array_map(function($row) {
            $pack = new self($row);
            $array = $pack->toArray();
            // Include items count
            $array['items'] = $pack->getItems();
            return $array;
        }, $results);
        
        return [
            'combo_packs' => $comboPacks,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get combo pack for printing
     */
    public static function getForPrint(int $comboPackId): ?array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM combo_packs WHERE id = ?");
        $stmt->execute([$comboPackId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get sales by pack report
     */
    public static function getSalesByPackReport(int $limit = 5): array {
        $conn = Database::getConnection();
        
        // Try to use view if exists, otherwise use direct query
        try {
            $stmt = $conn->query("SELECT * FROM combo_pack_performance_view ORDER BY total_orders DESC LIMIT ?");
            $stmt->execute([$limit]);
        } catch (\Exception $e) {
            // Fallback to direct query if view doesn't exist
            $stmt = $conn->prepare("
                SELECT 
                    cp.id,
                    cp.name,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(oi.total_price) as total_revenue
                FROM combo_packs cp
                LEFT JOIN order_items oi ON cp.id = oi.combo_pack_id
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE cp.is_active = 1
                GROUP BY cp.id
                ORDER BY total_orders DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get combo pack items
     */
    public function getItems(): array {
        if (!$this->id) {
            return [];
        }
        
        return ComboPackItem::getByComboPackId($this->id);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['price'] = $array['price'] ? (float)$array['price'] : null;
        $array['is_active'] = (bool)($array['is_active'] ?? false);
        
        // Include items if combo pack is loaded
        if ($this->id) {
            $array['items'] = $this->getItems();
        }
        
        return $array;
    }
}


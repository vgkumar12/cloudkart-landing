<?php

/**
 * Scheme Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Scheme extends Model {
    protected string $table = 'schemes';
    
    public ?int $id = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $frequency = null;
    public ?float $amount = null;
    public ?string $start_month = null;
    public ?int $duration_months = null;
    public ?int $bonus_months = null;
    public ?bool $is_active = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all active schemes
     */
    public static function getAll(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM schemes WHERE is_active = 1 ORDER BY id ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find scheme by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM schemes WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get subscriptions for this scheme
     */
    public function getSubscriptions(): array {
        if (!$this->id) {
            return [];
        }
        
        return SchemeSubscription::getBySchemeId($this->id);
    }
    
    /**
     * Get scheme statistics
     */
    public static function getStats(): array {
        $conn = Database::getConnection();
        
        $totalStmt = $conn->query("SELECT COUNT(*) FROM schemes");
        $total = (int)$totalStmt->fetchColumn();
        
        $activeStmt = $conn->query("SELECT COUNT(*) FROM schemes WHERE is_active = 1");
        $active = (int)$activeStmt->fetchColumn();
        
        return [
            'total' => $total,
            'active' => $active
        ];
    }
    
    /**
     * Get all schemes including inactive
     */
    public static function getAllWithInactive(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM schemes ORDER BY created_at DESC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Search schemes with pagination and filters
     */
    public static function searchWithPagination(?string $search = null, int $page = 1, int $limit = 20, ?string $sortBy = null, ?string $sortOrder = null, ?bool $includeInactive = false): array {
        $conn = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(name LIKE ? OR description LIKE ? OR frequency LIKE ?)";
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
        $countSql = "SELECT COUNT(*) FROM schemes WHERE {$whereClause}";
        $countParams = [];
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        // Validate and set sort parameters
        $allowedSortColumns = ['id', 'name', 'frequency', 'amount', 'duration_months', 'bonus_months', 'start_month', 'is_active', 'created_at', 'updated_at'];
        $sortColumn = 'id'; // Default
        $sortDirection = 'DESC'; // Default
        
        if ($sortBy && in_array($sortBy, $allowedSortColumns)) {
            $sortColumn = $sortBy;
        }
        
        if ($sortOrder && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($sortOrder);
        }
        
        // Get schemes
        $sql = "SELECT * FROM schemes 
                WHERE {$whereClause}
                ORDER BY {$sortColumn} {$sortDirection}
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $schemes = array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
        
        return [
            'schemes' => $schemes,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['amount'] = $array['amount'] ? (float)$array['amount'] : null;
        $array['duration_months'] = (int)($array['duration_months'] ?? 0);
        $array['bonus_months'] = (int)($array['bonus_months'] ?? 0);
        $array['is_active'] = (bool)($array['is_active'] ?? false);
        return $array;
    }
}



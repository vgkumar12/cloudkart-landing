<?php

/**
 * Slide Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Slide extends Model {
    protected string $table = 'slides';
    
    public ?int $id = null;
    public ?string $title = null;
    public ?string $subtitle = null;
    public ?string $description = null;
    public ?string $image_path = null;
    public ?string $link_url = null;
    public ?int $display_order = null;
    public ?bool $is_active = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all active slides
     */
    public static function getAll(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM slides WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find slide by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM slides WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get all slides including inactive
     */
    public static function getAllWithInactive(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM slides ORDER BY COALESCE(display_order, 999999), id ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Search slides with pagination and filters
     */
    public static function searchWithPagination(?string $search = null, int $page = 1, int $limit = 20, ?string $sortBy = null, ?string $sortOrder = null, ?bool $includeInactive = false): array {
        $conn = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(title LIKE ? OR subtitle LIKE ? OR description LIKE ?)";
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
        $countSql = "SELECT COUNT(*) FROM slides WHERE {$whereClause}";
        $countParams = [];
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        // Validate and set sort parameters
        $allowedSortColumns = ['id', 'title', 'display_order', 'is_active', 'created_at', 'updated_at'];
        $sortColumn = 'id'; // Default
        $sortDirection = 'DESC'; // Default
        
        if ($sortBy && in_array($sortBy, $allowedSortColumns)) {
            $sortColumn = $sortBy;
        }
        
        if ($sortOrder && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($sortOrder);
        }
        
        // Get slides
        $sql = "SELECT * FROM slides 
                WHERE {$whereClause}
                ORDER BY {$sortColumn} {$sortDirection}
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $slides = array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
        
        return [
            'slides' => $slides,
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
        $array['display_order'] = (int)($array['display_order'] ?? 0);
        $array['is_active'] = (bool)($array['is_active'] ?? false);
        return $array;
    }
}


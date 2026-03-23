<?php

/**
 * Category Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Category extends Model {
    protected string $table = 'categories';
    
    public ?int $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $description = null;
    public ?string $image_path = null;
    public ?string $thumb_path = null;
    public ?int $parent_id = null;
    public ?bool $is_active = null;
    public ?int $display_order = null;
    public ?string $meta_title = null;
    public ?string $meta_description = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all active categories
     */
    public static function getAll(?int $parentId = null): array {
        $conn = Database::getConnection();
        $sql = "SELECT * FROM categories WHERE is_active = 1";
        $params = [];
        
        if ($parentId !== null) {
            $sql .= " AND parent_id = ?";
            $params[] = $parentId;
        } else {
            $sql .= " AND (parent_id IS NULL OR parent_id = 0)";
        }
        
        $sql .= " ORDER BY display_order ASC, name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find category by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find category by slug
     */
    public static function findBySlug(string $slug): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Search categories with pagination and filters
     */
    public static function searchWithPagination(?string $search = null, ?int $parentId = null, int $page = 1, int $limit = 20, ?string $sortBy = null, ?string $sortOrder = null): array {
        $conn = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(name LIKE ? OR slug LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($parentId !== null) {
            $whereConditions[] = "parent_id = ?";
            $params[] = $parentId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM categories WHERE {$whereClause}";
        $countParams = [];
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
        }
        if ($parentId !== null) {
            $countParams[] = $parentId;
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        // Validate and set sort parameters
        $allowedSortColumns = ['id', 'name', 'slug', 'parent_id', 'display_order', 'is_active', 'created_at', 'updated_at'];
        $sortColumn = 'id'; // Default
        $sortDirection = 'DESC'; // Default
        
        if ($sortBy && in_array($sortBy, $allowedSortColumns)) {
            $sortColumn = $sortBy;
        }
        
        if ($sortOrder && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($sortOrder);
        }
        
        // Get categories with parent name via LEFT JOIN
        $sql = "SELECT c.*, p.name AS parent_name 
                FROM categories c 
                LEFT JOIN categories p ON c.parent_id = p.id 
                WHERE {$whereClause}
                ORDER BY c.{$sortColumn} {$sortDirection}
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $categories = array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
        
        return [
            'categories' => $categories,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get all active categories for price list
     */
    public static function getAllActive(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['is_active'] = (bool)($array['is_active'] ?? false);
        return $array;
    }
}


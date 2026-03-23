<?php

/**
 * Brand Model
 * Handles database queries and business logic for brands
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Brand extends Model {
    protected string $table = 'brands';
    
    public ?int $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $description = null;
    public ?string $logo_path = null;
    public ?string $website_url = null;
    public ?bool $is_active = true;
    public ?int $display_order = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all brands
     */
    public static function getAll(bool $activeOnly = true): array {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table}";
        
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        
        $sql .= " ORDER BY display_order ASC, name ASC";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute();
        
        $brands = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $brands[] = new static($row);
        }
        
        return $brands;
    }
    
    /**
     * Get brands with product count
     */
    public static function getWithProductCount(): array {
        $instance = new static();
        $sql = "SELECT b.*, COUNT(p.id) as product_count 
                FROM {$instance->table} b
                LEFT JOIN products p ON p.brand_id = b.id AND p.is_active = 1
                GROUP BY b.id
                ORDER BY b.display_order ASC, b.name ASC";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find brand by slug
     */
    public static function findBySlug(string $slug) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new static($row) : null;
    }
    
    /**
     * Find brand by ID
     */
    public static function findById(int $id) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new static($row) : null;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'website_url' => $this->website_url,
            'is_active' => (bool)$this->is_active,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

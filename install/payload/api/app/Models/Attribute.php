<?php

/**
 * Attribute Model
 * Handles database queries and business logic for product attributes
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Attribute extends Model {
    protected string $table = 'attributes';
    
    public ?int $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $type = 'select';
    public ?bool $is_required = false;
    public ?bool $is_filterable = true;
    public ?bool $is_visible = true;
    public ?int $display_order = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all attributes
     */
    public static function getAll(): array {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} ORDER BY display_order ASC, name ASC";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute();
        
        $attributes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $attributes[] = new static($row);
        }
        
        return $attributes;
    }
    
    /**
     * Get all attributes with their values
     */
    public static function getAllWithValues(): array {
        $instance = new static();
        
        // First get all attributes
        $sql = "SELECT * FROM {$instance->table} ORDER BY display_order ASC, name ASC";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute();
        
        $attributes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Get values for this attribute
            $valuesSql = "SELECT id, value, slug, color_code, weight_value, display_order 
                         FROM attribute_values 
                         WHERE attribute_id = ? 
                         ORDER BY display_order ASC, value ASC";
            $valuesStmt = $instance->db->prepare($valuesSql);
            $valuesStmt->execute([$row['id']]);
            
            $row['values'] = $valuesStmt->fetchAll(PDO::FETCH_ASSOC);
            $attributes[] = $row;
        }
        
        return $attributes;
    }
    
    
    /**
     * Get filterable attributes for product filters
     */
    public static function getFilterable(): array {
        $instance = new static();
        
        // First get filterable attributes
        $sql = "SELECT * FROM {$instance->table} WHERE is_filterable = 1 ORDER BY display_order ASC";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute();
        
        $attributes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Get values for this attribute
            $valuesSql = "SELECT id, value, slug, color_code, weight_value 
                         FROM attribute_values 
                         WHERE attribute_id = ? 
                         ORDER BY display_order ASC, value ASC";
            $valuesStmt = $instance->db->prepare($valuesSql);
            $valuesStmt->execute([$row['id']]);
            
            $row['values'] = $valuesStmt->fetchAll(PDO::FETCH_ASSOC);
            $attributes[] = $row;
        }
        
        return $attributes;
    }
    
    
    /**
     * Find attribute by ID
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
            'type' => $this->type,
            'is_required' => (bool)$this->is_required,
            'is_filterable' => (bool)$this->is_filterable,
            'is_visible' => (bool)$this->is_visible,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

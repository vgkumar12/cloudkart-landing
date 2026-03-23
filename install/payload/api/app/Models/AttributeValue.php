<?php

/**
 * AttributeValue Model
 * Handles database queries and business logic for attribute values
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class AttributeValue extends Model {
    protected string $table = 'attribute_values';
    
    public ?int $id = null;
    public ?int $attribute_id = null;
    public ?string $value = null;
    public ?string $slug = null;
    public ?string $color_code = null;
    public ?float $weight_value = null;
    public ?int $display_order = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get values by attribute ID
     */
    public static function getByAttribute(int $attributeId): array {
        $instance = new static();
        $stmt = $instance->db->prepare(
            "SELECT * FROM {$instance->table} 
             WHERE attribute_id = ? 
             ORDER BY display_order ASC, value ASC"
        );
        $stmt->execute([$attributeId]);
        
        $values = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values[] = new static($row);
        }
        
        return $values;
    }
    
    /**
     * Find attribute value by ID
     */
    public static function findById(int $id) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new static($row) : null;
    }
    
    /**
     * Get weight value in kg (for Package Size attributes)
     */
    public function getWeightInKg(): ?float {
        return $this->weight_value;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'attribute_id' => $this->attribute_id,
            'value' => $this->value,
            'slug' => $this->slug,
            'color_code' => $this->color_code,
            'weight_value' => $this->weight_value,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

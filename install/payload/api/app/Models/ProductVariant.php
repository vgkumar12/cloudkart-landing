<?php

/**
 * ProductVariant Model
 * Handles database queries and business logic for product variants
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class ProductVariant extends Model {
    protected string $table = 'product_variants';
    
    public ?int $id = null;
    public ?int $product_id = null;
    public ?string $sku = null;
    public ?int $stock_quantity = 0;
    public ?float $price = null;
    public ?float $sale_price = null;
    public ?float $weight = null;
    public ?bool $is_active = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all variants for a product
     */
    public static function getByProduct(int $productId): array {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} WHERE product_id = ? ORDER BY id ASC";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([$productId]);
        
        $variants = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $variants[] = new static($row);
        }
        
        return $variants;
    }
    
    /**
     * Get variant by SKU
     */
    public static function findBySku(string $sku) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE sku = ?");
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new static($row) : null;
    }
    
    /**
     * Get variant by ID
     */
    public static function findById(int $id) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new static($row) : null;
    }
    
    /**
     * Get attribute values for this variant
     */
    public function getAttributes(): array {
        $sql = "SELECT av.*, a.name as attribute_name, a.type as attribute_type
                FROM product_variant_attributes pva
                JOIN attribute_values av ON av.id = pva.attribute_value_id
                JOIN attributes a ON a.id = av.attribute_id
                WHERE pva.variant_id = ?
                ORDER BY a.display_order ASC, av.display_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Link attribute values to this variant
     */
    public function syncAttributes(array $attributeValueIds): void {
        // Delete existing links
        $stmt = $this->db->prepare("DELETE FROM product_variant_attributes WHERE variant_id = ?");
        $stmt->execute([$this->id]);
        
        // Insert new links
        $stmt = $this->db->prepare("INSERT INTO product_variant_attributes (variant_id, attribute_value_id) VALUES (?, ?)");
        foreach ($attributeValueIds as $valueId) {
            $stmt->execute([$this->id, $valueId]);
        }
    }
    
    /**
     * Update stock quantity
     */
    public function updateStock(int $quantity): bool {
        $this->stock_quantity = $quantity;
        return $this->update(['stock_quantity' => $quantity]);
    }
    
    /**
     * Get total stock for a product (sum of all variants)
     */
    public static function getTotalStockForProduct(int $productId): int {
        $instance = new static();
        $sql = "SELECT SUM(stock_quantity) as total FROM {$instance->table} 
                WHERE product_id = ? AND is_active = 1";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([$productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Convert to array with attributes
     */
    public function toArray(): array {
        $data = [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'stock_quantity' => $this->stock_quantity,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'weight' => $this->weight,
            'is_active' => (bool)$this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
        
        // Include attributes if variant has ID
        if ($this->id) {
            $data['attributes'] = $this->getAttributes();
        }
        
        return $data;
    }
}

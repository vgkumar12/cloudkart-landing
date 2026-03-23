<?php

/**
 * Product Model
 * Handles database queries and business logic for products
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Product extends Model {
    protected string $table = 'products';
    
    // Properties (will be populated from database)
    public ?int $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $description = null;
    public ?string $short_description = null;
    public ?string $sku = null;
    public ?float $price = null;
    public ?float $sale_price = null;
    public ?float $cost_price = null;
    public ?float $wholesale_rate = null;
    public ?int $quantity_per_carton = null;
    public ?float $wholesale_rate_per_carton = null;
    public ?int $stock_quantity = null;
    public ?int $min_stock_level = null;
    public ?int $max_stock_level = null;
    public ?float $weight = null;
    public ?string $dimensions = null;
    public ?string $image_path = null;
    public ?string $thumb_path = null;
    public ?string $gallery_images = null;
    public ?string $video_url = null;
    public ?int $category_id = null;
    public ?int $brand_id = null;
    public ?bool $is_active = null;
    public ?bool $is_featured = null;
    public ?bool $is_digital = null;
    public ?bool $requires_shipping = null;
    public ?bool $has_variants = false;
    public ?float $tax_rate = null;
    public ?string $meta_title = null;
    public ?string $meta_description = null;
    public ?string $tags = null;
    public ?int $display_order = null;
    public ?string $category = null;
    public ?string $category_name = null;
    public ?string $category_slug = null;
    public ?string $unit_type = null;
    public ?float $list_price = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get all active products
     */
    public static function getAll(?int $categoryId = null, ?bool $featured = null): array {
        $conn = Database::getConnection();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.is_active = 1";
        $params = [];
        
        if ($categoryId !== null) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        
        if ($featured !== null && $featured) {
            $sql .= " AND p.is_featured = 1";
        }
        
        $sql .= " ORDER BY p.display_order ASC, p.id ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find product by ID
     */
    public static function findById(int $id): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find product by slug
     */
    public static function findBySlug(string $slug): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.slug = ? AND p.is_active = 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get featured products
     */
    public static function getFeatured(int $limit = 10): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.is_active = 1 AND p.is_featured = 1 
                                ORDER BY p.display_order ASC, p.id ASC 
                                LIMIT ?");
        $stmt->execute([$limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Search products
     */
    public static function search(string $query, int $limit = 50): array {
        $conn = Database::getConnection();
        $searchTerm = "%{$query}%";
        $stmt = $conn->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?) 
                                ORDER BY p.display_order ASC, p.id ASC 
                                LIMIT ?");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Find product by SKU
     */
    public static function findBySku(string $sku): ?self {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Search products with pagination and filters
     * Includes category information via JOIN
     */
    public static function searchWithPagination(?string $search = null, ?int $categoryId = null, int $page = 1, int $limit = 20, ?string $sortBy = null, ?string $sortOrder = null): array {
        $conn = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($categoryId) {
            $whereConditions[] = "p.category_id = ?";
            $params[] = $categoryId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count (for pagination)
        $countSql = "SELECT COUNT(*) FROM products p WHERE {$whereClause}";
        $countParams = [];
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
        }
        if ($categoryId) {
            $countParams[] = $categoryId;
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();
        
        // Validate and set sort parameters
        $allowedSortColumns = ['id', 'name', 'sku', 'price', 'sale_price', 'stock_quantity', 'category_id', 'is_active', 'display_order', 'created_at', 'updated_at'];
        $sortColumn = 'id'; // Default
        $sortDirection = 'DESC'; // Default
        
        if ($sortBy && in_array($sortBy, $allowedSortColumns)) {
            $sortColumn = $sortBy;
        }
        
        if ($sortOrder && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($sortOrder);
        }
        
        // Get products with category information
        // Note: $sortColumn is validated against whitelist above, so it's safe to use
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE {$whereClause}
                ORDER BY p.{$sortColumn} {$sortDirection}
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $products = array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
        
        return [
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get all products with categories for price list
     */
    public static function getAllWithCategories(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("
            SELECT p.*, c.id AS category_id, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY p.id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get count of active products
     */
    public static function getActiveCount(): int {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT COUNT(*) FROM products WHERE is_active = 1");
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get products with wholesale rates for POS
     */
    public static function getForWholesalePos(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("
            SELECT id, name, price, sale_price, wholesale_rate, 
                   quantity_per_carton, wholesale_rate_per_carton, 
                   stock_quantity, image_path, sku, category_id
            FROM products 
            WHERE is_active = 1 
              AND (wholesale_rate_per_carton IS NOT NULL AND wholesale_rate_per_carton > 0)
            ORDER BY category_id ASC, id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get product performance report
     */
    public static function getPerformanceReport(int $limit = 50): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("
            SELECT 
                p.id,
                p.name,
                p.sku,
                COUNT(oi.id) as times_ordered,
                SUM(oi.quantity) as total_quantity_sold,
                SUM(oi.total_price) as total_revenue
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            WHERE p.is_active = 1
            GROUP BY p.id
            ORDER BY total_revenue DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Business Logic: Get discounted price
     */
    public function getDiscountedPrice(): float {
        return $this->sale_price ?? $this->price ?? 0.0;
    }
    
    /**
     * Business Logic: Check if in stock
     */
    public function isInStock(): bool {
        return $this->getAvailableStock() > 0;
    }
    
    /**
     * Business Logic: Check if low stock
     */
    public function isLowStock(): bool {
        $minLevel = $this->min_stock_level ?? 5;
        return ($this->stock_quantity ?? 0) <= $minLevel;
    }
    
    /**
     * Business Logic: Reduce stock quantity
     */
    public function reduceStock(int $quantity): bool {
        if ($this->id === null) {
            throw new \Exception("Cannot reduce stock for unsaved product");
        }
        
        $newQuantity = max(0, ($this->stock_quantity ?? 0) - $quantity);
        return $this->update(['stock_quantity' => $newQuantity]);
    }
    
    /**
     * Business Logic: Get wholesale price per unit
     */
    public function getWholesalePricePerUnit(): ?float {
        if ($this->wholesale_rate_per_carton && $this->quantity_per_carton) {
            return $this->wholesale_rate_per_carton / $this->quantity_per_carton;
        }
        return $this->wholesale_rate;
    }
    
    /**
     * Get brand for this product
     */
    public function getBrand(): ?array {
        if (!$this->brand_id) {
            return null;
        }
        
        $brand = \App\Models\Brand::findById($this->brand_id);
        return $brand ? $brand->toArray() : null;
    }
    
    /**
     * Get attributes for this product
     */
    public function getAttributes(): array {
        if (!$this->id) {
            return [];
        }
        
        $conn = Database::getConnection();
        $sql = "SELECT a.id, a.name, a.slug, a.type,
                       av.id as value_id, av.value, av.slug as value_slug, 
                       av.color_code, av.weight_value
                FROM product_attribute_values pav
                JOIN attribute_values av ON pav.attribute_value_id = av.id
                JOIN attributes a ON av.attribute_id = a.id
                WHERE pav.product_id = ?
                ORDER BY a.display_order ASC, av.display_order ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$this->id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by attribute
        $attributes = [];
        foreach ($results as $row) {
            $attrId = $row['id'];
            if (!isset($attributes[$attrId])) {
                $attributes[$attrId] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'type' => $row['type'],
                    'values' => []
                ];
            }
            $attributes[$attrId]['values'][] = [
                'id' => $row['value_id'],
                'value' => $row['value'],
                'slug' => $row['value_slug'],
                'color_code' => $row['color_code'],
                'weight_value' => $row['weight_value'] ? (float)$row['weight_value'] : null
            ];
        }
        
        return array_values($attributes);
    }
    
    /**
     * Sync product attributes
     * @param array $attributeValueIds Array of attribute_value IDs
     */
    public function syncAttributes(array $attributeValueIds): bool {
        if (!$this->id) {
            throw new \Exception("Cannot sync attributes for unsaved product");
        }
        
        $conn = Database::getConnection();
        
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Delete existing attribute associations
            $deleteStmt = $conn->prepare("DELETE FROM product_attribute_values WHERE product_id = ?");
            $deleteStmt->execute([$this->id]);
            
            // Insert new associations
            if (!empty($attributeValueIds)) {
                $insertStmt = $conn->prepare(
                    "INSERT INTO product_attribute_values (product_id, attribute_value_id) VALUES (?, ?)"
                );
                
                foreach ($attributeValueIds as $valueId) {
                    $insertStmt->execute([$this->id, $valueId]);
                }
            }
            
            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Convert to array with computed fields
     */
    
    /**
     * Check if product has variants
     */
    public function hasVariants(): bool {
        return (bool)$this->has_variants;
    }
    
    /**
     * Get all variants for this product
     */
    public function getVariants(): array {
        if (!$this->hasVariants()) {
            return [];
        }
        
        return ProductVariant::getByProduct($this->id);
    }
    
    /**
     * Get available stock (sum of variants or product stock)
     */
    public function getAvailableStock(): int {
        if ($this->hasVariants()) {
            return ProductVariant::getTotalStockForProduct($this->id);
        }
        return (int)$this->stock_quantity;
    }
    
    public function toArray(): array {

        $array = parent::toArray();
        
        // Add computed fields
        $array['discounted_price'] = $this->getDiscountedPrice();
        $array['is_in_stock'] = $this->isInStock();
        $array['is_low_stock'] = $this->isLowStock();
        $array['wholesale_price_per_unit'] = $this->getWholesalePricePerUnit();
        
        // Parse JSON fields
        if (isset($array['gallery_images']) && is_string($array['gallery_images'])) {
            $array['gallery_images'] = json_decode($array['gallery_images'], true) ?? [];
        }
        if (isset($array['tags']) && is_string($array['tags'])) {
            $array['tags'] = json_decode($array['tags'], true) ?? [];
        }
        
        // Type casting
        $array['price'] = $array['price'] ? (float)$array['price'] : null;
        $array['sale_price'] = $array['sale_price'] ? (float)$array['sale_price'] : null;
        $array['stock_quantity'] = (int)($array['stock_quantity'] ?? 0);
        $array['is_active'] = (bool)($array['is_active'] ?? false);
        $array['is_featured'] = (bool)($array['is_featured'] ?? false);
        
        // Add brand and attributes if ID exists
        if ($this->id) {
            $array['brand'] = $this->getBrand();
            $array['attributes'] = $this->getAttributes();
        }
        
        return $array;
    }
}


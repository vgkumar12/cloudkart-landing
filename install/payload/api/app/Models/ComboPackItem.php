<?php

/**
 * Combo Pack Item Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class ComboPackItem extends Model {
    protected string $table = 'combo_pack_items';
    
    public ?int $id = null;
    public ?int $combo_pack_id = null;
    public ?int $category_id = null;
    public ?string $category = null;
    public ?string $item_name = null;
    public ?int $quantity = null;
    public ?string $unit_type = null;
    public ?float $unit_price = null;
    public ?float $total_price = null;
    public ?string $image_path = null;
    public ?int $display_order = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get items by combo pack ID
     */
    public static function getByComboPackId(int $comboPackId): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM combo_pack_items WHERE combo_pack_id = ? ORDER BY display_order ASC, id ASC");
        $stmt->execute([$comboPackId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['quantity'] = (int)($array['quantity'] ?? 0);
        $array['unit_price'] = $array['unit_price'] ? (float)$array['unit_price'] : null;
        $array['total_price'] = $array['total_price'] ? (float)$array['total_price'] : null;
        return $array;
    }
}


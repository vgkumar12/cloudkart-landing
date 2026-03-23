<?php

/**
 * Setting Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Setting extends Model {
    protected string $table = 'settings';
    
    public ?int $id = null;
    public ?string $setting_key = null;
    public ?string $setting_value = null;
    public ?string $group_name = null;
    public ?string $setting_type = null;
    public ?bool $is_public = null;
    public ?string $description = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    
    /**
     * Get setting by key
     */
    public static function get(string $key, $default = null) {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("SELECT * FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return $default;
        }
        
        $setting = new self($row);
        return $setting->getValue();
    }
    
    /**
     * Set setting value
     */
    public static function set(string $key, $value, string $group = 'general', string $type = 'string', bool $isPublic = true): bool {
        $conn = Database::getConnection();
        
        // Serialize value
        $serialized = self::serializeValue($value, $type);
        
        // UPSERT logic
        $sql = "INSERT INTO settings (setting_key, setting_value, group_name, setting_type, is_public) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                group_name = VALUES(group_name), 
                setting_type = VALUES(setting_type), 
                is_public = VALUES(is_public)";
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$key, $serialized, $group, $type, $isPublic ? 1 : 0]);
    }
    
    /**
     * Get public settings for frontend
     */
    public static function getPublicSettings(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM settings WHERE is_public = 1");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $setting = new self($row);
            $settings[$row['setting_key']] = $setting->getValue();
        }
        
        return $settings;
    }
    
    /**
     * Get all settings grouped (for admin UI)
     */
    public static function getAllGrouped(): array {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT * FROM settings ORDER BY group_name, setting_key");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $grouped = [];
        foreach ($results as $row) {
            $setting = (new self($row))->toArray();
            $grouped[$row['group_name']][] = $setting;
        }
        
        return $grouped;
    }
    
    /**
     * Get value (deserialized based on type)
     */
    public function getValue() {
        $value = $this->setting_value;
        
        switch ($this->setting_type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Serialize value based on type
     */
    private static function serializeValue($value, string $type): string {
        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }
        if ($type === 'json') {
            return json_encode($value);
        }
        return (string)$value;
    }
    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'key' => $this->setting_key,
            'value' => $this->getValue(),
            'group' => $this->group_name,
            'type' => $this->setting_type,
            'is_public' => (bool)$this->is_public,
            'description' => $this->description
        ];
    }
}




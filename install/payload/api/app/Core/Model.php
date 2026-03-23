<?php

/**
 * Base Model Class
 * Provides common database operations and property access
 */

namespace App\Core;

use PDO;

abstract class Model {
    protected string $table = '';
    protected array $fillable = [];
    protected array $hidden = [];
    protected ?PDO $db = null;
    
    /**
     * Constructor - populate model from array
     */
    public function __construct(array $data = []) {
        $this->db = Database::getConnection();
        
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Get all records
     */
    public static function all(): array {
        $instance = new static();
        $stmt = $instance->db->query("SELECT * FROM {$instance->table}");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return new static($row);
        }, $results);
    }
    
    /**
     * Find record by ID
     */
    public static function find(int $id) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? new static($row) : null;
    }
    
    /**
     * Find record by ID or fail
     */
    public static function findOrFail(int $id) {
        $model = self::find($id);
        if (!$model) {
            throw new \Exception("Record not found with ID: {$id}");
        }
        return $model;
    }
    
    /**
     * Create new record
     */
    public static function create(array $data) {
        $instance = new static();
        
        // Filter data - use fillable if defined, otherwise use all provided data
        $fillableData = [];
        if (!empty($instance->fillable)) {
            // Use fillable fields if defined
            foreach ($instance->fillable as $field) {
                if (isset($data[$field])) {
                    $fillableData[$field] = $data[$field];
                }
            }
        } else {
            // No fillable defined - use all data (but exclude non-column fields)
            $fillableData = $data;
        }
        
        if (empty($fillableData)) {
            throw new \Exception("No data provided");
        }
        
        $fields = implode(', ', array_keys($fillableData));
        $placeholders = implode(', ', array_fill(0, count($fillableData), '?'));
        
        $sql = "INSERT INTO {$instance->table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $instance->db->prepare($sql);
        $stmt->execute(array_values($fillableData));
        
        $id = $instance->db->lastInsertId();
        return self::find($id);
    }
    
    /**
     * Update record
     */
    public function update(array $data): bool {
        if (!isset($this->id)) {
            throw new \Exception("Cannot update model without ID");
        }
        
        // Filter data - use fillable if defined, otherwise use all provided data
        $fillableData = [];
        if (!empty($this->fillable)) {
            // Use fillable fields if defined
            foreach ($this->fillable as $field) {
                if (isset($data[$field])) {
                    $fillableData[$field] = $data[$field];
                }
            }
        } else {
            // No fillable defined - use all data
            $fillableData = $data;
        }
        
        if (empty($fillableData)) {
            return false;
        }
        
        $setClause = [];
        foreach (array_keys($fillableData) as $field) {
            $setClause[] = "{$field} = ?";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE id = ?";
        $values = array_merge(array_values($fillableData), [$this->id]);
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Delete record
     */
    public function delete(): bool {
        if (!isset($this->id)) {
            throw new \Exception("Cannot delete model without ID");
        }
        
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Save model (insert or update)
     */
    public function save(): bool {
        if (isset($this->id)) {
            // Get current data from model properties
            $data = [];
            $reflection = new \ReflectionClass($this);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);
            
            foreach ($properties as $property) {
                $name = $property->getName();
                if ($name === 'db' || $name === 'table' || $name === 'fillable' || $name === 'hidden') {
                    continue;
                }
                if (isset($this->$name)) {
                    $data[$name] = $this->$name;
                }
            }
            
            return $this->update($data);
        } else {
            // Insert new
            $data = [];
            $reflection = new \ReflectionClass($this);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);
            
            foreach ($properties as $property) {
                $name = $property->getName();
                if ($name === 'db' || $name === 'table' || $name === 'fillable' || $name === 'hidden') {
                    continue;
                }
                if (isset($this->$name)) {
                    $data[$name] = $this->$name;
                }
            }
            
            $newModel = self::create($data);
            $this->id = $newModel->id;
            return true;
        }
    }
    
    /**
     * Convert model to array
     */
    public function toArray(): array {
        $array = [];
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);
        
        foreach ($properties as $property) {
            $name = $property->getName();
            
            // Skip hidden fields
            if (in_array($name, $this->hidden)) {
                continue;
            }
            
            // Skip database connection
            if ($name === 'db') {
                continue;
            }
            
            $array[$name] = $this->$name ?? null;
        }
        
        return $array;
    }
    
    /**
     * Convert model to JSON
     */
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Get table name
     */
    public function getTable(): string {
        return $this->table;
    }
}


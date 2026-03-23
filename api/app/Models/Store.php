<?php

namespace App\Models;

use App\Core\Model;

class Store extends Model {
    protected $table = 'platform_stores';

    public static function findBySubdomain($subdomain) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM platform_stores WHERE subdomain = ?");
        $stmt->execute([$subdomain]);
        return $stmt->fetch();
    }

    public static function create($data) {
        $instance = new static();
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $stmt = $instance->db->prepare("INSERT INTO platform_stores ($fields) VALUES ($placeholders)");
        if (!$stmt->execute(array_values($data))) {
            throw new \Exception("Failed to insert store record");
        }
        $id = $instance->db->lastInsertId();
        if (!$id || $id == 0) {
            // Log for debugging
            error_log("Store record created but lastInsertId returned 0. Data: " . json_encode($data));
        }
        return (int)$id;
    }

    public static function deleteById($id) {
        $instance = new static();
        $stmt = $instance->db->prepare("DELETE FROM platform_stores WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

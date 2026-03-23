<?php

namespace App\Core;

use PDO;

abstract class Model {
    protected $table;
    protected $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public static function all() {
        $instance = new static();
        $stmt = $instance->db->query("SELECT * FROM {$instance->table}");
        return $stmt->fetchAll();
    }

    public static function find($id) {
        $instance = new static();
        $stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}

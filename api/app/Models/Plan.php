<?php

namespace App\Models;

use App\Core\Model;

class Plan extends Model {
    protected $table = 'platform_plans';

    public static function getActive() {
        $instance = new static();
        $stmt = $instance->db->query("SELECT * FROM platform_plans WHERE is_active = 1");
        return $stmt->fetchAll();
    }
}

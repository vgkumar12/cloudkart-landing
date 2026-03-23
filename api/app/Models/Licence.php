<?php

namespace App\Models;

use App\Core\Model;

class Licence extends Model {
    protected $table = 'platform_licences';

    public static function validate($key) {
        $instance = new static();
        $stmt = $instance->db->prepare("
            SELECT l.*, s.store_name, s.subdomain 
            FROM platform_licences l
            JOIN platform_stores s ON l.store_id = s.id
            WHERE l.licence_key = ? AND l.status = 'active'
        ");
        $stmt->execute([$key]);
        return $stmt->fetch();
    }
}

<?php

namespace App\Core;

class Request {
    public function all() {
        $data = array_merge($_GET, $_POST);
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) {
            $data = array_merge($data, $json);
        }
        return $data;
    }

    public function input($key, $default = null) {
        $all = $this->all();
        return $all[$key] ?? $default;
    }
}

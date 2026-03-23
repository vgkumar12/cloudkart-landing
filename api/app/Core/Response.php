<?php

namespace App\Core;

class Response {
    public function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function success($data = [], $message = 'Success') {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    public function error($message = 'Error', $status = 400) {
        $this->json([
            'success' => false,
            'message' => $message
        ], $status);
    }
}

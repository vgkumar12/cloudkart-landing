<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Helpers\Auth;

class AuthController {
    public function login() {
        $request = new Request();
        $response = new Response();
        $db = Database::getConnection();

        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            $response->error("Email and password are required");
        }

        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                unset($user['password']);

                // Generate a signed HMAC token — role is baked in server-side
                $token = Auth::generateToken((int)$user['id'], $user['role']);

                $response->success([
                    'user'           => $user,
                    'token'          => $token,
                    'is_super_admin' => ($user['role'] === 'admin'),
                ], "Login successful");
            } else {
                $response->error("Invalid email or password");
            }
        } catch (\Exception $e) {
            $response->error("Login failed: " . $e->getMessage());
        }
    }

    public function checkSession() {
        // Placeholder for session check logic
        $response = new Response();
        $response->success([], "Authenticated");
    }
}

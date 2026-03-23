<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class RegistrationController {
    public function register() {
        $request = new Request();
        $response = new Response();
        $db = Database::getConnection();

        $name = $request->input('name');
        $email = $request->input('email');
        $phone = $request->input('phone');
        $password = $request->input('password');

        if (!$name || !$email || !$password) {
            $response->error("Missing required fields");
        }

        try {
            // Check if user exists in main users table
            $stmt = $db->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                // If user exists, verify password to allow them to proceed with a new store attempt
                if (password_verify($password, $existingUser['password'])) {
                    $response->success(['user_id' => $existingUser['id']], "Welcome back! Proceeding with store creation.");
                    return;
                } else {
                    $response->error("Email already registered. Please use the correct password to continue.");
                    return;
                }
            }

            // Create user with role 'owner'
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, 'owner', 1)");
            $stmt->execute([$name, $email, $phone, $passwordHash]);
            
            $userId = (int)$db->lastInsertId();
            
            if (!$userId || $userId <= 0) {
                // If ID is 0, it means AUTO_INCREMENT is likely missing on the 'users' table
                throw new \Exception("System Error: User ID generated as 0. Please ensure the 'users' table has AUTO_INCREMENT enabled on the 'id' column.");
            }

            $response->success(['user_id' => $userId], "Registration successful");
        } catch (\Exception $e) {
            $response->error("Registration failed: " . $e->getMessage());
        }
    }
}

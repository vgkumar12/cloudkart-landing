<?php
/**
 * Super Admin Creation Utility
 * Run this script to create a default Super Admin account for the platform.
 */

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/app/Core/Database.php';

use App\Core\Database;

try {
    $db = Database::getPlatformConnection(); // platform master-admin DB
    
    $name = 'System Admin';
    $email = 'admin@cloudkart.com';
    $password = 'admin123';
    $role = 'admin'; // 'admin' is the Super Admin role on the platform
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $stmt = $db->prepare("UPDATE users SET password = ?, role = ? WHERE email = ?");
        $stmt->execute([$passwordHash, $role, $email]);
        echo "Super Admin updated successfully!\n";
    } else {
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$name, $email, $passwordHash, $role]);
        echo "Super Admin created successfully!\n";
    }
    
    echo "Login Email: $email\n";
    echo "Password: $password\n";
    echo "URL: http://localhost/cloudkart/landing/login.html\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

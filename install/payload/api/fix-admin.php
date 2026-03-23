<?php
/**
 * Fix Admin User Script
 * Sets the admin role and password for the admin user
 */

// Include config
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Database;

Autoloader::register();

echo "=== Fix Admin User ===\n\n";

$adminEmail = 'admin@suncrackers.in';
$adminUsername = 'admin';
$adminPassword = 'diwali2024';

try {
    $conn = Database::getConnection();
    
    // Find admin user
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1");
    $stmt->execute([$adminEmail, $adminUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Admin user not found. Creating new admin user...\n";
        
        // Create admin user
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, 'admin', 1, NOW(), NOW())
        ");
        $stmt->execute([$adminUsername, $adminEmail, $hashedPassword]);
        
        echo "✅ Admin user created successfully!\n";
        echo "   Username: {$adminUsername}\n";
        echo "   Email: {$adminEmail}\n";
        echo "   Password: {$adminPassword}\n";
        echo "   Role: admin\n";
    } else {
        echo "✅ Found existing user:\n";
        echo "   ID: {$user['id']}\n";
        echo "   Name: {$user['name']}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Current Role: {$user['role']}\n";
        echo "   Has Password: " . (!empty($user['password']) ? 'Yes' : 'No') . "\n\n";
        
        // Update user to admin role and set password
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users 
            SET role = 'admin', 
                password = ?, 
                is_active = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);
        
        echo "✅ Admin user updated successfully!\n";
        echo "   Username: {$user['name']}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Password: {$adminPassword}\n";
        echo "   Role: admin (updated)\n";
    }
    
    echo "\n=== Admin user is ready! ===\n";
    echo "\nYou can now login with:\n";
    echo "   Username: {$adminUsername}\n";
    echo "   Password: {$adminPassword}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

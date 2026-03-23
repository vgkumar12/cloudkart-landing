<?php
/**
 * Admin User Verification Script
 * Run this to check if admin user exists and verify password
 */

// Include config
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Models\User;

Autoloader::register();

echo "=== Admin User Verification ===\n\n";

// Check for admin user
$adminEmail = 'admin@suncrackers.in';
$adminUsername = 'admin';
$testPassword = 'diwali2024';

echo "Searching for admin user...\n";
echo "Email: {$adminEmail}\n";
echo "Username: {$adminUsername}\n\n";

// Try to find by email
$userByEmail = User::findByEmailOrName($adminEmail);
if ($userByEmail) {
    echo "✅ Found user by email: {$adminEmail}\n";
    echo "   ID: {$userByEmail->id}\n";
    echo "   Name: {$userByEmail->name}\n";
    echo "   Email: {$userByEmail->email}\n";
    echo "   Role: {$userByEmail->role}\n";
    echo "   Active: " . ($userByEmail->is_active ? 'Yes' : 'No') . "\n";
    echo "   Has password: " . (!empty($userByEmail->password) ? 'Yes' : 'No') . "\n";
    
    if (!empty($userByEmail->password)) {
        echo "\n   Testing password '{$testPassword}'...\n";
        $verified = $userByEmail->verifyPassword($testPassword);
        echo "   Password verification: " . ($verified ? '✅ SUCCESS' : '❌ FAILED') . "\n";
        
        if (!$verified) {
            echo "\n   ⚠️ Password verification failed!\n";
            echo "   You may need to reset the admin password.\n";
        }
    }
} else {
    echo "❌ User not found by email: {$adminEmail}\n";
}

echo "\n";

// Try to find by username
$userByUsername = User::findByEmailOrName($adminUsername);
if ($userByUsername) {
    echo "✅ Found user by username: {$adminUsername}\n";
    echo "   ID: {$userByUsername->id}\n";
    echo "   Name: {$userByUsername->name}\n";
    echo "   Email: {$userByUsername->email}\n";
    echo "   Role: {$userByUsername->role}\n";
    echo "   Active: " . ($userByUsername->is_active ? 'Yes' : 'No') . "\n";
    echo "   Has password: " . (!empty($userByUsername->password) ? 'Yes' : 'No') . "\n";
    
    if (!empty($userByUsername->password)) {
        echo "\n   Testing password '{$testPassword}'...\n";
        $verified = $userByUsername->verifyPassword($testPassword);
        echo "   Password verification: " . ($verified ? '✅ SUCCESS' : '❌ FAILED') . "\n";
        
        if (!$verified) {
            echo "\n   ⚠️ Password verification failed!\n";
            echo "   You may need to reset the admin password.\n";
        }
    }
} else {
    echo "❌ User not found by username: {$adminUsername}\n";
}

echo "\n=== End of Verification ===\n";
